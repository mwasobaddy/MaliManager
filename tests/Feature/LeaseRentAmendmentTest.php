<?php

use App\Models\Lease;
use App\Models\LeaseRentChange;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Property;
use App\Models\User;
use App\Services\OccupantService;
use App\Services\RentArrearsService;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function rentAmendOrganization(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Amend Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function rentAmendProperty(Organization $organization, User $owner): Property
{
    return $organization->properties()->create([
        'name' => 'Amend Court',
        'slug' => 'amend-court',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function rentAmendUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

function rentAmendLeaseUrl(Property $property, Lease $lease): string
{
    return "/{$property->slug}/leases/{$lease->id}/amend-rent";
}

/**
 * A moved-in occupant through the real path, so the inception row is
 * seeded exactly as production does it.
 *
 * @return array{Unit, Occupant, Lease}
 */
function rentAmendSetup(Organization $organization, User $owner, Property $property, string $name, string $rent = '25000', string $startsAt = '2026-01-01'): array
{
    $unit = $property->units()->create(['name' => $name, 'status' => 'vacant', 'created_by' => $owner->id]);
    $occupant = app(OccupantService::class)->create($organization, $property, $owner, [
        'first_name' => 'Jane',
        'last_name' => "Amend-{$name}",
        'email' => "jane-amend-{$name}@x.test",
        'status' => 'active',
        'unit_ids' => [$unit->id],
        'lease' => [
            'starts_at' => $startsAt,
            'rent_amount' => $rent,
            'rent_frequency' => 'monthly',
            'rent_due_day' => 5,
            'currency' => 'KES',
        ],
    ]);

    return [$unit, $occupant, Lease::where('unit_id', $unit->id)->firstOrFail()];
}

test('amending rent stores a dated row and updates the lease', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = rentAmendOrganization($owner);
    $property = rentAmendProperty($organization, $owner);
    [, , $lease] = rentAmendSetup($organization, $owner, $property, 'A1');

    $this->actingAs($owner)
        ->post(rentAmendUrl($organization, rentAmendLeaseUrl($property, $lease)), [
            'rent_amount' => '20000',
            'effective_date' => '2026-06-01',
        ])
        ->assertRedirect();

    expect(LeaseRentChange::where('lease_id', $lease->id)->count())->toBe(2)
        ->and(LeaseRentChange::where('lease_id', $lease->id)->whereDate('effective_date', '2026-06-01')->firstOrFail()->rent_amount)->toEqual(20000)
        ->and($lease->fresh()->rent_amount)->toEqual(20000);
});

test('the inception row captures the booked terms', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = rentAmendOrganization($owner);
    $property = rentAmendProperty($organization, $owner);
    [, , $lease] = rentAmendSetup($organization, $owner, $property, 'A1');

    $row = LeaseRentChange::where('lease_id', $lease->id)->sole();

    expect($row->effective_date->toDateString())->toBe('2026-01-01')
        ->and($row->rent_amount)->toEqual(25000);
});

test('amendment needs a positive amount and a start-bound date', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = rentAmendOrganization($owner);
    $property = rentAmendProperty($organization, $owner);
    [, , $lease] = rentAmendSetup($organization, $owner, $property, 'A1');
    $url = rentAmendUrl($organization, rentAmendLeaseUrl($property, $lease));

    $this->actingAs($owner)
        ->from(rentAmendUrl($organization, "/{$property->slug}/leases"))
        ->post($url, ['rent_amount' => '0', 'effective_date' => '2026-06-01'])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'above zero'
        ));

    $this->actingAs($owner)
        ->from(rentAmendUrl($organization, "/{$property->slug}/leases"))
        ->post($url, ['rent_amount' => '20000', 'effective_date' => '2025-12-01'])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'before the lease started'
        ));

    expect(LeaseRentChange::where('lease_id', $lease->id)->count())->toBe(1)
        ->and($lease->fresh()->rent_amount)->toEqual(25000);
});

test('same-date amendment updates instead of duplicating', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = rentAmendOrganization($owner);
    $property = rentAmendProperty($organization, $owner);
    [, , $lease] = rentAmendSetup($organization, $owner, $property, 'A1');
    $url = rentAmendUrl($organization, rentAmendLeaseUrl($property, $lease));

    $this->actingAs($owner)->post($url, ['rent_amount' => '20000', 'effective_date' => '2026-06-01'])->assertRedirect();
    $this->actingAs($owner)->post($url, ['rent_amount' => '22000', 'effective_date' => '2026-06-01'])->assertRedirect();

    expect(LeaseRentChange::where('lease_id', $lease->id)->count())->toBe(2)
        ->and(LeaseRentChange::where('lease_id', $lease->id)->whereDate('effective_date', '2026-06-01')->firstOrFail()->rent_amount)->toEqual(22000)
        ->and($lease->fresh()->rent_amount)->toEqual(22000);
});

test('a backdated amendment reprices only later periods', function () {
    $this->travelTo('2026-11-10');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = rentAmendOrganization($owner);
    $property = rentAmendProperty($organization, $owner);
    [, , $lease] = rentAmendSetup($organization, $owner, $property, 'A1');

    // Due on the 5th: Jan–Nov is 11 dues at 25000 before the amendment.
    expect(app(RentArrearsService::class)->balanceFor($lease->fresh())['expected'])->toBe('275000.00');

    $this->actingAs($owner)
        ->post(rentAmendUrl($organization, rentAmendLeaseUrl($property, $lease)), [
            'rent_amount' => '20000',
            'effective_date' => '2026-06-01',
        ])
        ->assertRedirect();

    // Jan–May at 25000 (125000) plus Jun–Nov at 20000 (120000). The
    // move-in step already banked the first 25000, so 220000 is still owed.
    $balance = app(RentArrearsService::class)->balanceFor($lease->fresh());

    expect($balance['expected'])->toBe('245000.00')
        ->and($balance['paid'])->toBe('25000.00')
        ->and($balance['outstanding'])->toBe('220000.00');
});

test('a future-dated amendment leaves current expected untouched', function () {
    $this->travelTo('2026-11-10');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = rentAmendOrganization($owner);
    $property = rentAmendProperty($organization, $owner);
    [, , $lease] = rentAmendSetup($organization, $owner, $property, 'A1');

    $this->actingAs($owner)
        ->post(rentAmendUrl($organization, rentAmendLeaseUrl($property, $lease)), [
            'rent_amount' => '20000',
            'effective_date' => '2026-12-01',
        ])
        ->assertRedirect();

    expect(app(RentArrearsService::class)->balanceFor($lease->fresh())['expected'])->toBe('275000.00');
});

test('amending an ended lease is rejected', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = rentAmendOrganization($owner);
    $property = rentAmendProperty($organization, $owner);
    [, , $lease] = rentAmendSetup($organization, $owner, $property, 'A1');

    $lease->update(['status' => 'ended', 'ends_at' => '2026-10-01']);

    $this->actingAs($owner)
        ->from(rentAmendUrl($organization, "/{$property->slug}/leases"))
        ->post(rentAmendUrl($organization, rentAmendLeaseUrl($property, $lease)), [
            'rent_amount' => '20000',
            'effective_date' => '2026-11-01',
        ])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'active lease'
        ));

    expect(LeaseRentChange::where('lease_id', $lease->id)->count())->toBe(1);
});

test('updating an occupant never rewrites the live lease rent', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = rentAmendOrganization($owner);
    $property = rentAmendProperty($organization, $owner);
    [$unit, $occupant, $lease] = rentAmendSetup($organization, $owner, $property, 'A1');

    $this->actingAs($owner)
        ->put(rentAmendUrl($organization, "/{$property->slug}/occupants/{$occupant->id}"), [
            'first_name' => 'Jane',
            'email' => 'jane-amend-A1@x.test',
            'status' => 'active',
            'unit_ids' => [$unit->id],
            'lease' => ['rent_amount' => 20000, 'rent_frequency' => 'monthly', 'currency' => 'KES'],
        ])
        ->assertRedirect();

    expect($lease->fresh()->rent_amount)->toEqual(25000)
        ->and(LeaseRentChange::where('lease_id', $lease->id)->count())->toBe(1);
});

test('amending a history-less lease backfills its inception rate', function () {
    $this->travelTo('2026-11-10');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = rentAmendOrganization($owner);
    $property = rentAmendProperty($organization, $owner);
    $unit = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'created_by' => $owner->id]);
    $person = Person::create(['first_name' => 'Jane', 'email' => 'jane-legacy@x.test', 'status' => 'active']);
    $lease = Lease::create([
        'person_id' => $person->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'starts_at' => '2026-08-01',
        'rent_amount' => 10000,
        'rent_frequency' => 'monthly',
        'rent_due_day' => 5,
        'currency' => 'KES',
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->post(rentAmendUrl($organization, rentAmendLeaseUrl($property, $lease)), [
            'rent_amount' => '8000',
            'effective_date' => '2026-10-01',
        ])
        ->assertRedirect();

    // Inception backfilled at the old rate, so Aug–Sep stay at 10000 while
    // Oct–Nov bill 8000: 20000 + 16000.
    expect(LeaseRentChange::where('lease_id', $lease->id)->count())->toBe(2)
        ->and(LeaseRentChange::where('lease_id', $lease->id)->whereDate('effective_date', '2026-08-01')->firstOrFail()->rent_amount)->toEqual(10000)
        ->and(app(RentArrearsService::class)->balanceFor($lease->fresh())['expected'])->toBe('36000.00')
        ->and($lease->fresh()->rent_amount)->toEqual(8000);
});

test('leases without history fall back to their current rent', function () {
    $this->travelTo('2026-11-10');

    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = rentAmendOrganization($owner);
    $property = rentAmendProperty($organization, $owner);
    $unit = $property->units()->create(['name' => 'A1', 'status' => 'occupied', 'created_by' => $owner->id]);
    $person = Person::create(['first_name' => 'Jane', 'email' => 'jane-legacy@x.test', 'status' => 'active']);
    $lease = Lease::create([
        'person_id' => $person->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'starts_at' => '2026-08-01',
        'rent_amount' => 10000,
        'rent_frequency' => 'monthly',
        'rent_due_day' => 5,
        'currency' => 'KES',
        'status' => 'active',
    ]);

    // Due on the 5th: Aug, Sep, Oct, Nov = 4 x 10000.
    expect(app(RentArrearsService::class)->balanceFor($lease->refresh())['expected'])->toBe('40000.00');
});
