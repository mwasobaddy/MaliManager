<?php

use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Property;
use App\Models\User;
use App\Services\OccupantService;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function attentionOrganization(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Attention Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function attentionProperty(Organization $organization, User $owner): Property
{
    return $organization->properties()->create([
        'name' => 'Attention Court',
        'slug' => 'attention-court',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function attentionUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

/**
 * One occupant holding the given units, every lease backdated so all are
 * overdue unless settled.
 *
 * @return array{Occupant, Lease[]}
 */
function attentionSetup(Organization $organization, User $owner, Property $property, string $name, array $unitNames): array
{
    $unitIds = [];
    foreach ($unitNames as $unitName) {
        $unitIds[] = $property->units()->create(['name' => $unitName, 'status' => 'vacant', 'created_by' => $owner->id])->id;
    }

    $occupant = app(OccupantService::class)->create($organization, $property, $owner, [
        'first_name' => $name,
        'email' => strtolower($name).'@x.test',
        'status' => 'active',
        'unit_ids' => $unitIds,
        'lease' => ['starts_at' => '2026-08-01', 'rent_amount' => '10000', 'rent_frequency' => 'monthly', 'rent_due_day' => 5, 'currency' => 'KES'],
    ]);

    return [$occupant, Lease::whereIn('unit_id', $unitIds)->orderBy('id')->get()->all()];
}

function attentionRows(Organization $organization, Property $property): array
{
    $owner = $organization->users()->firstOrFail();

    return test()->actingAs($owner)
        ->get(attentionUrl($organization, "/{$property->slug}/occupants"))
        ->assertOk()
        ->viewData('page')['props']['occupants']['data'];
}

test('a single overdue lease rides along for the row remind button', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = attentionOrganization($owner);
    $property = attentionProperty($organization, $owner);
    [$occupant, $leases] = attentionSetup($organization, $owner, $property, 'Solo', ['A1']);

    $rows = attentionRows($organization, $property);
    $row = collect($rows)->firstWhere('id', $occupant->id);

    expect($row['attention'])->not->toBeNull()
        ->and($row['attention']['lease_id'])->toBe($leases[0]->id)
        ->and((float) $row['attention']['outstanding'])->toBeGreaterThan(0)
        ->and($row['active_lease_id'])->toBe($leases[0]->id);
});

test('zero or several overdue leases on one row offer no row shortcut', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = attentionOrganization($owner);
    $property = attentionProperty($organization, $owner);

    // Settled in full: nothing overdue.
    [$settled] = attentionSetup($organization, $owner, $property, 'Settled', ['A1']);
    foreach (Lease::where('occupant_id', $settled->id)->get() as $lease) {
        $lease->payments()->create([
            'organization_id' => $organization->id,
            'lease_id' => $lease->id,
            'occupant_id' => $settled->id,
            'amount' => '999999.00',
            'currency' => 'KES',
            'paid_on' => now()->toDateString(),
            'status' => 'received',
            'type' => 'rent',
            'created_by' => $owner->id,
        ]);
    }

    // One person, two overdue tenancies: separate rows, each with its own
    // single-overdue shortcut.
    [$multi] = attentionSetup($organization, $owner, $property, 'Multi', ['B1', 'B2']);

    // Ended only: history, nothing due.
    [$ended, $endedLeases] = attentionSetup($organization, $owner, $property, 'Ended', ['C1']);
    app(OccupantService::class)->terminateLease($endedLeases[0]);

    // Two overdue leases stacked on one row: ambiguous, the sheet takes over.
    $stacked = Occupant::create([
        'organization_id' => $organization->id,
        'person_id' => $ended->person_id,
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
    $stackedUnit = $property->units()->create(['name' => 'D1', 'status' => 'vacant', 'created_by' => $owner->id]);
    $stacked->units()->attach($stackedUnit->id, ['created_by' => $owner->id]);
    foreach (['2026-08-01', '2025-08-01'] as $startsAt) {
        Lease::create([
            'person_id' => $ended->person_id,
            'organization_id' => $organization->id,
            'tenant_id' => $organization->tenant_id,
            'property_id' => $property->id,
            'unit_id' => $stackedUnit->id,
            'occupant_id' => $stacked->id,
            'starts_at' => $startsAt,
            'rent_amount' => '10000',
            'rent_frequency' => 'monthly',
            'currency' => 'KES',
            'status' => 'active',
            'created_by' => $owner->id,
        ]);
    }

    $rows = attentionRows($organization, $property);
    $byId = collect($rows)->keyBy('id');

    expect($byId[$settled->id]['attention'])->toBeNull()
        ->and($byId[$settled->id]['active_lease_id'])->not->toBeNull();

    $multiRows = Occupant::where('organization_id', $organization->id)->where('person_id', $multi->person_id)->get();
    expect($multiRows)->toHaveCount(2);
    foreach ($multiRows as $row) {
        expect($byId[$row->id]['attention'])->not->toBeNull()
            ->and($byId[$row->id]['attention']['lease_id'])->toBe(Lease::where('occupant_id', $row->id)->where('status', 'active')->value('id'));
    }

    expect($byId[$ended->id]['attention'])->toBeNull()
        ->and($byId[$ended->id]['active_lease_id'])->toBeNull()
        ->and($byId[$stacked->id]['attention'])->toBeNull();
});

test('the sheet lists every tenancy with its live balance', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = attentionOrganization($owner);
    $property = attentionProperty($organization, $owner);
    [$occupant, $leases] = attentionSetup($organization, $owner, $property, 'Hist', ['A1']);

    $ended = Lease::create([
        'person_id' => $occupant->person_id,
        'organization_id' => $organization->id,
        'tenant_id' => $organization->tenant_id,
        'property_id' => $property->id,
        'unit_id' => $leases[0]->unit_id,
        'occupant_id' => $occupant->id,
        'starts_at' => '2025-01-01',
        'ends_at' => '2025-06-01',
        'rent_amount' => '8000',
        'rent_frequency' => 'monthly',
        'currency' => 'KES',
        'status' => 'ended',
        'created_by' => $owner->id,
    ]);

    $focused = test()->actingAs($owner)
        ->get(attentionUrl($organization, "/{$property->slug}/occupants?mode=view&occupant={$occupant->id}"))
        ->assertOk()
        ->viewData('page')['props']['focusedOccupant'];

    expect($focused['leases'])->toHaveCount(2)
        ->and($focused['leases'][0]['status'])->toBe('active')
        ->and($focused['leases'][0]['is_overdue'])->toBeTrue()
        ->and((float) $focused['leases'][0]['outstanding'])->toBeGreaterThan(0)
        ->and($focused['leases'][0]['has_agreement'])->toBeTrue()
        ->and($focused['leases'][1]['id'])->toBe($ended->id)
        ->and($focused['leases'][1]['is_overdue'])->toBeFalse();
});
