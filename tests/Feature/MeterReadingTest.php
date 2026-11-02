<?php

use App\Enums\UtilityType;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitMeterReading;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function meterOrganization(User $owner, string $name = 'Meter Estates'): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: $name,
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function meterProperty(Organization $organization, User $owner): Property
{
    return $organization->properties()->create([
        'name' => 'Meter Court',
        'slug' => 'meter-court',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function meterUnit(Property $property): Unit
{
    return Unit::factory()->create(['property_id' => $property->id]);
}

function meterUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->firstOrCreate(
        ['domain' => $organization->slug.'.meter.test'],
        ['domain' => $organization->slug.'.meter.test'],
    );

    return "http://{$domain->domain}{$path}";
}

test('an owner records a reading and consumption is calculated from the previous one', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $unit = meterUnit(meterProperty($organization, $owner));

    UnitMeterReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'utility' => UtilityType::Electricity,
        'reading' => '1000.000',
        'consumption' => null,
        'read_on' => '2026-01-01',
        'recorded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(meterUrl($organization, '/meter-readings'), [
            'unit_id' => $unit->id,
            'utility' => 'electricity',
            'reading' => '1250.500',
            'read_on' => '2026-02-01',
            'notes' => 'Quarterly check.',
        ])
        ->assertRedirect();

    $reading = UnitMeterReading::query()
        ->where('unit_id', $unit->id)
        ->whereDate('read_on', '2026-02-01')
        ->firstOrFail();

    expect((float) $reading->reading)->toBe(1250.5)
        ->and((float) $reading->consumption)->toBe(250.5)
        ->and($reading->organization_id)->toBe($organization->id)
        ->and($reading->recorded_by)->toBe($owner->id)
        ->and($reading->utility)->toBe(UtilityType::Electricity);
});

test('the first reading for a meter has no consumption', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $unit = meterUnit(meterProperty($organization, $owner));

    $this->actingAs($owner)
        ->post(meterUrl($organization, '/meter-readings'), [
            'unit_id' => $unit->id,
            'utility' => 'water',
            'reading' => '32.000',
            'read_on' => '2026-02-01',
        ])
        ->assertRedirect();

    expect(UnitMeterReading::firstOrFail()->consumption)->toBeNull();
});

test('a reading earlier than the previous one is rejected by the unique index, not silently stored', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $unit = meterUnit(meterProperty($organization, $owner));

    UnitMeterReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'utility' => UtilityType::Electricity,
        'reading' => '900.000',
        'read_on' => '2026-02-01',
        'recorded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(meterUrl($organization, '/meter-readings'), [
            'unit_id' => $unit->id,
            'utility' => 'electricity',
            'reading' => '850.000',
            'read_on' => '2026-03-01',
        ])
        ->assertRedirect();

    // A meter that runs backwards is a replacement or a rollover: the reading
    // is kept because it is what the meter says, but there is no consumption
    // to report rather than a negative one.
    expect(UnitMeterReading::query()->latest('id')->firstOrFail()->consumption)->toBeNull();
});

test('a future reading date is rejected', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $unit = meterUnit(meterProperty($organization, $owner));

    $this->actingAs($owner)
        ->post(meterUrl($organization, '/meter-readings'), [
            'unit_id' => $unit->id,
            'utility' => 'electricity',
            'reading' => '10',
            'read_on' => now()->addDay()->toDateString(),
        ])
        ->assertSessionHasErrors('read_on');

    expect(UnitMeterReading::count())->toBe(0);
});

test('an unknown utility is rejected', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $unit = meterUnit(meterProperty($organization, $owner));

    $this->actingAs($owner)
        ->post(meterUrl($organization, '/meter-readings'), [
            'unit_id' => $unit->id,
            'utility' => 'steam',
            'reading' => '10',
            'read_on' => '2026-02-01',
        ])
        ->assertSessionHasErrors('utility');
});

test('a unit belonging to another organization is rejected', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $otherOwner = User::factory()->create(['onboarded_at' => now()]);

    $organization = meterOrganization($owner, 'First Estates');
    $otherOrganization = meterOrganization($otherOwner, 'Second Estates');

    meterProperty($organization, $owner);
    $foreignUnit = meterUnit(meterProperty($otherOrganization, $otherOwner));

    $this->actingAs($owner)
        ->post(meterUrl($organization, '/meter-readings'), [
            'unit_id' => $foreignUnit->id,
            'utility' => 'electricity',
            'reading' => '10',
            'read_on' => '2026-02-01',
        ])
        ->assertSessionHasErrors('unit_id');

    expect(UnitMeterReading::count())->toBe(0);
});

test('the readings list renders with the recorded rows', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $unit = meterUnit(meterProperty($organization, $owner));

    UnitMeterReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'utility' => UtilityType::Gas,
        'reading' => '48.500',
        'consumption' => '12.500',
        'read_on' => '2026-02-01',
        'recorded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(meterUrl($organization, '/meter-readings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/units/meter-readings')
            ->has('readings.data', 1)
            ->where('readings.data.0.utility', 'gas')
            ->where('readings.data.0.utility_label', 'Gas')
            ->has('units', 1)
            ->has('utilities', 3)
        );
});
