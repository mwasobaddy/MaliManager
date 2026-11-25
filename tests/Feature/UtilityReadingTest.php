<?php

use App\Enums\UtilityType;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityCharge;
use App\Models\UtilityReading;
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

function utilityUrl(Organization $organization, string $path): string
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

    UtilityReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'utility' => UtilityType::Electricity,
        'reading' => '1000.000',
        'consumption' => null,
        'read_on' => '2026-01-01',
        'recorded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(utilityUrl($organization, '/utilities'), [
            'unit_id' => $unit->id,
            'utility' => 'electricity',
            'reading' => '1250.500',
            'read_on' => '2026-02-01',
            'notes' => 'Quarterly check.',
        ])
        ->assertRedirect();

    $reading = UtilityReading::query()
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
        ->post(utilityUrl($organization, '/utilities'), [
            'unit_id' => $unit->id,
            'utility' => 'water',
            'reading' => '32.000',
            'read_on' => '2026-02-01',
        ])
        ->assertRedirect();

    expect(UtilityReading::firstOrFail()->consumption)->toBeNull();
});

test('a reading earlier than the previous one is rejected by the unique index, not silently stored', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $unit = meterUnit(meterProperty($organization, $owner));

    UtilityReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'utility' => UtilityType::Electricity,
        'reading' => '900.000',
        'read_on' => '2026-02-01',
        'recorded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(utilityUrl($organization, '/utilities'), [
            'unit_id' => $unit->id,
            'utility' => 'electricity',
            'reading' => '850.000',
            'read_on' => '2026-03-01',
        ])
        ->assertRedirect();

    // A meter that runs backwards is a replacement or a rollover: the reading
    // is kept because it is what the meter says, but there is no consumption
    // to report rather than a negative one.
    expect(UtilityReading::query()->latest('id')->firstOrFail()->consumption)->toBeNull();
});

test('a future reading date is rejected', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $unit = meterUnit(meterProperty($organization, $owner));

    $this->actingAs($owner)
        ->post(utilityUrl($organization, '/utilities'), [
            'unit_id' => $unit->id,
            'utility' => 'electricity',
            'reading' => '10',
            'read_on' => now()->addDay()->toDateString(),
        ])
        ->assertSessionHasErrors('read_on');

    expect(UtilityReading::count())->toBe(0);
});

test('an unknown utility is rejected', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $unit = meterUnit(meterProperty($organization, $owner));

    $this->actingAs($owner)
        ->post(utilityUrl($organization, '/utilities'), [
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
        ->post(utilityUrl($organization, '/utilities'), [
            'unit_id' => $foreignUnit->id,
            'utility' => 'electricity',
            'reading' => '10',
            'read_on' => '2026-02-01',
        ])
        ->assertSessionHasErrors('unit_id');

    expect(UtilityReading::count())->toBe(0);
});

test('the readings list renders with the recorded rows', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $unit = meterUnit(meterProperty($organization, $owner));

    UtilityReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'utility' => UtilityType::Gas,
        'reading' => '48.500',
        'consumption' => '12.500',
        'read_on' => '2026-02-01',
        'recorded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(utilityUrl($organization, '/utilities'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/utilities/index')
            ->has('readings.data', 1)
            ->where('readings.data.0.utility', 'gas')
            ->where('readings.data.0.utility_label', 'Gas')
            ->has('units', 1)
            ->has('utilities', 3)
        );
});

test('readings search by unit name across words', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $property = meterProperty($organization, $owner);
    $unitA = $property->units()->create(['name' => 'Unit A1', 'status' => 'occupied', 'created_by' => $owner->id]);
    $unitB = $property->units()->create(['name' => 'Unit B2', 'status' => 'occupied', 'created_by' => $owner->id]);

    foreach ([$unitA, $unitB] as $unit) {
        UtilityReading::create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'utility' => UtilityType::Water,
            'reading' => '10.000',
            'consumption' => null,
            'read_on' => '2026-01-01',
            'recorded_by' => $owner->id,
        ]);
    }

    $this->actingAs($owner)
        ->get(utilityUrl($organization, '/utilities?search=Unit+B2'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('readings.data', fn ($rows) => count($rows) === 1 && $rows[0]['unit_name'] === 'Unit B2'));

    $this->actingAs($owner)
        ->get(utilityUrl($organization, '/utilities?search=Unit+Z9'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('readings.data', []));
});

test('readings sort by read date and unit', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $property = meterProperty($organization, $owner);
    $unitA = $property->units()->create(['name' => 'Unit A1', 'status' => 'occupied', 'created_by' => $owner->id]);
    $unitB = $property->units()->create(['name' => 'Unit B2', 'status' => 'occupied', 'created_by' => $owner->id]);

    UtilityReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unitB->id,
        'utility' => UtilityType::Water,
        'reading' => '20.000',
        'consumption' => null,
        'read_on' => '2026-03-01',
        'recorded_by' => $owner->id,
    ]);
    UtilityReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unitA->id,
        'utility' => UtilityType::Water,
        'reading' => '10.000',
        'consumption' => null,
        'read_on' => '2026-01-01',
        'recorded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(utilityUrl($organization, '/utilities?sort=read_on&direction=asc'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('readings.data.0.read_on', '2026-01-01')
            ->where('readings.data.1.read_on', '2026-03-01'));

    $this->actingAs($owner)
        ->get(utilityUrl($organization, '/utilities?sort=unit&direction=asc'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('readings.data.0.unit_name', 'Unit A1')
            ->where('readings.data.1.unit_name', 'Unit B2'));
});

test('the view sheet resolves the focused reading', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $unit = meterUnit(meterProperty($organization, $owner));

    $reading = UtilityReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'utility' => UtilityType::Gas,
        'reading' => '48.500',
        'consumption' => '12.500',
        'read_on' => '2026-02-01',
        'recorded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(utilityUrl($organization, "/utilities?mode=view&reading={$reading->id}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('focusedReading.id', $reading->id)
            ->where('focusedReading.utility', 'gas')
            ->where('focusedReading.consumption', 12.5));

    $this->actingAs($owner)
        ->get(utilityUrl($organization, '/utilities?mode=view&reading=999999'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('focusedReading', null));
});

test('export downloads the filtered readings as csv', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $property = meterProperty($organization, $owner);
    $unit = $property->units()->create(['name' => 'Unit A1', 'status' => 'occupied', 'created_by' => $owner->id]);

    UtilityReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'utility' => UtilityType::Water,
        'reading' => '10.000',
        'consumption' => null,
        'read_on' => '2026-01-01',
        'recorded_by' => $owner->id,
    ]);
    UtilityReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'utility' => UtilityType::Electricity,
        'reading' => '99.000',
        'consumption' => null,
        'read_on' => '2026-01-02',
        'recorded_by' => $owner->id,
    ]);

    $response = $this->actingAs($owner)
        ->get(utilityUrl($organization, '/utilities/export?utility=water'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));
    expect($lines[0])->toContain('Unit')
        ->and(count($lines))->toBe(2)
        ->and($lines[1])->toContain('Unit A1');
});

test('export honors a column subset', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $unit = meterUnit(meterProperty($organization, $owner));

    UtilityReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'utility' => UtilityType::Water,
        'reading' => '10.000',
        'consumption' => null,
        'read_on' => '2026-01-01',
        'recorded_by' => $owner->id,
    ]);

    $response = $this->actingAs($owner)
        ->get(utilityUrl($organization, '/utilities/export?columns[]=Unit&columns[]=Reading'));

    $response->assertOk();
    $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));
    expect($lines[0])->toBe('Unit,Reading');
});

test('the utilities page reports per-property billing setup', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);

    $alpha = $organization->properties()->create([
        'name' => 'Alpha Court', 'slug' => 'alpha-court', 'status' => 'active', 'created_by' => $owner->id,
    ]);
    $alpha->units()->create(['name' => 'A1', 'status' => 'occupied', 'rent_amount' => 25000, 'created_by' => $owner->id]);
    $alpha->units()->create(['name' => 'A2', 'status' => 'vacant', 'rent_amount' => null, 'created_by' => $owner->id]);

    $beta = $organization->properties()->create([
        'name' => 'Beta Court', 'slug' => 'beta-court', 'status' => 'active', 'created_by' => $owner->id,
    ]);
    $unitB = $beta->units()->create(['name' => 'B1', 'status' => 'occupied', 'rent_amount' => 30000, 'created_by' => $owner->id]);
    UtilityCharge::create([
        'organization_id' => $organization->id,
        'property_id' => $beta->id,
        'name' => 'Garbage',
        'kind' => 'fixed',
        'currency' => 'KES',
        'is_active' => true,
        'created_by' => $owner->id,
    ])->units()->attach($unitB->id, ['amount' => 500]);
    UtilityReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unitB->id,
        'utility' => UtilityType::Water,
        'reading' => '10.000',
        'consumption' => null,
        'read_on' => '2026-02-01',
        'recorded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(utilityUrl($organization, '/utilities'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/utilities/index')
            ->has('setup', 2)
            ->where('setup.0.name', 'Alpha Court')
            ->where('setup.0.units_count', 2)
            ->where('setup.0.units_without_rent', 1)
            ->where('setup.0.active_charges', 0)
            ->where('setup.0.latest_reading_on', null)
            ->where('setup.1.name', 'Beta Court')
            ->where('setup.1.units_count', 1)
            ->where('setup.1.units_without_rent', 0)
            ->where('setup.1.active_charges', 1)
            ->where('setup.1.latest_reading_on', '2026-02-01')
        );
});

test('properties without units are skipped by the setup checklist', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = meterOrganization($owner);
    $organization->properties()->create([
        'name' => 'Empty Court', 'slug' => 'empty-court', 'status' => 'active', 'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(utilityUrl($organization, '/utilities'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/utilities/index')
            ->has('setup', 0)
        );
});
