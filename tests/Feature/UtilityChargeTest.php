<?php

use App\Enums\UtilityType;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityCharge;
use App\Models\UtilityReading;
use App\Services\TenantService;
use App\Support\LeaseAgreementTemplate;
use App\Support\UtilityBilling;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function utilityOrganization(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Utility Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function utilityProperty(Organization $organization, User $owner): Property
{
    return $organization->properties()->create([
        'name' => 'Meter Court',
        'slug' => 'meter-court',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function utilityChargeUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

function chargePayload(Property $property, array $unitIds, array $overrides = []): array
{
    return array_merge([
        'property_id' => $property->id,
        'name' => 'Water',
        'kind' => 'metered',
        'utility' => 'water',
        'rate' => '120.50',
        'currency' => 'KES',
        'unit_ids' => $unitIds,
    ], $overrides);
}

function makeUnitWithLease(Organization $organization, User $owner, Property $property, string $name): array
{
    $unit = $property->units()->create(['name' => $name, 'status' => 'occupied', 'created_by' => $owner->id]);
    $person = Person::create(['first_name' => 'Jane', 'email' => "jane-{$name}@x.test", 'status' => 'active']);
    $occupant = Occupant::create([
        'organization_id' => $organization->id,
        'person_id' => $person->id,
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
    $lease = Lease::create([
        'person_id' => $person->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'occupant_id' => $occupant->id,
        'starts_at' => '2026-01-01',
        'rent_amount' => 25000,
        'rent_frequency' => 'monthly',
        'currency' => 'KES',
        'status' => 'active',
    ]);

    return [$unit, $lease];
}

test('a metered charge records its rate and backfills the billing watermark', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = utilityOrganization($owner);
    $property = utilityProperty($organization, $owner);
    [$unit] = [makeUnitWithLease($organization, $owner, $property, 'A1')[0]];

    UtilityReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'utility' => UtilityType::Water,
        'reading' => '100.000',
        'read_on' => '2026-01-01',
        'recorded_by' => $owner->id,
    ]);
    $latest = UtilityReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'utility' => UtilityType::Water,
        'reading' => '150.000',
        'read_on' => '2026-02-01',
        'recorded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$unit->id]))
        ->assertRedirect();

    $charge = UtilityCharge::firstOrFail();
    expect($charge->kind->value)->toBe('metered')
        ->and((float) $charge->rate)->toBe(120.50)
        // Backfilled: only consumption *after* setup bills.
        ->and($charge->units()->first()->pivot->last_billed_reading_id)->toBe($latest->id);
});

test('fixed charges require a per-unit amount', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = utilityOrganization($owner);
    $property = utilityProperty($organization, $owner);
    [$unit] = [makeUnitWithLease($organization, $owner, $property, 'A1')[0]];

    $this->actingAs($owner)
        ->from(utilityChargeUrl($organization, '/utilities'))
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$unit->id], [
            'kind' => 'fixed',
            'utility' => null,
            'rate' => null,
        ]))
        ->assertRedirect(utilityChargeUrl($organization, '/utilities'))
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'needs an amount'
        ));

    expect(UtilityCharge::count())->toBe(0);

    $this->actingAs($owner)
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$unit->id], [
            'kind' => 'fixed',
            'utility' => null,
            'rate' => null,
            'amounts' => [$unit->id => '500'],
        ]))
        ->assertRedirect();

    expect(UtilityCharge::firstOrFail()->units()->first()->pivot->amount)->toEqual(500);
});

test('a metered charge needs a utility and a rate', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = utilityOrganization($owner);
    $property = utilityProperty($organization, $owner);
    [$unit] = [makeUnitWithLease($organization, $owner, $property, 'A1')[0]];

    $this->actingAs($owner)
        ->from(utilityChargeUrl($organization, '/utilities'))
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$unit->id], [
            'utility' => null,
            'rate' => null,
        ]))
        ->assertRedirect(utilityChargeUrl($organization, '/utilities'))
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'needs a utility'
        ));

    expect(UtilityCharge::count())->toBe(0);
});

test('charges reject units from another property', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = utilityOrganization($owner);
    $property = utilityProperty($organization, $owner);
    $other = $property->replicate(['slug']);
    $other->slug = 'other-court';
    $other->save();
    $foreign = $other->units()->create(['name' => 'Z9', 'status' => 'vacant', 'created_by' => $owner->id]);

    $this->actingAs($owner)
        ->from(utilityChargeUrl($organization, '/utilities'))
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$foreign->id]))
        ->assertRedirect(utilityChargeUrl($organization, '/utilities'));

    expect(UtilityCharge::count())->toBe(0);
});

test('the lease total adds fixed and metered lines to rent', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = utilityOrganization($owner);
    $property = utilityProperty($organization, $owner);
    [$unit, $lease] = makeUnitWithLease($organization, $owner, $property, 'A1');

    UtilityReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'utility' => UtilityType::Water,
        'reading' => '100.000',
        'read_on' => '2026-01-01',
        'recorded_by' => $owner->id,
    ]);

    // Fixed garbage 500 on the unit.
    $this->actingAs($owner)
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$unit->id], [
            'name' => 'Garbage',
            'kind' => 'fixed',
            'utility' => null,
            'rate' => null,
            'amounts' => [$unit->id => '500'],
        ]))
        ->assertRedirect();

    // Metered water at 10/unit: backfill marks 100, then 150 arrives.
    $this->actingAs($owner)
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$unit->id], [
            'name' => 'Water',
            'rate' => '10',
        ]))
        ->assertRedirect();

    UtilityReading::create([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'utility' => UtilityType::Water,
        'reading' => '150.000',
        'consumption' => '50.000',
        'read_on' => '2026-02-01',
        'recorded_by' => $owner->id,
    ]);

    $breakdown = UtilityBilling::breakdownForLease($lease->fresh());

    expect($breakdown['fixed'])->toBe('500.00')
        ->and($breakdown['metered'])->toBe('500.00')
        ->and($breakdown['total'])->toBe('26000.00')
        ->and($breakdown['lines'])->toHaveCount(2);

    $this->actingAs($owner)
        ->get(utilityChargeUrl($organization, "/meter-court/leases?mode=view&lease={$lease->id}"))
        ->assertOk();
});

test('property charges reject the retired one-time kind', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = utilityOrganization($owner);
    $property = utilityProperty($organization, $owner);
    [$unit] = [makeUnitWithLease($organization, $owner, $property, 'A1')[0]];

    // Once-off fees live on the lease as additional charges now; the
    // property template only bills recurring kinds.
    $this->actingAs($owner)
        ->from(utilityChargeUrl($organization, '/utilities'))
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$unit->id], [
            'name' => 'Move-in cleaning',
            'kind' => 'one_time',
            'utility' => null,
            'rate' => null,
            'amounts' => [$unit->id => '200'],
        ]))
        ->assertRedirect(utilityChargeUrl($organization, '/utilities'))
        ->assertSessionHasErrors('kind');

    expect(UtilityCharge::count())->toBe(0);
});

test('inactive charges are excluded from the total', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = utilityOrganization($owner);
    $property = utilityProperty($organization, $owner);
    [$unit, $lease] = makeUnitWithLease($organization, $owner, $property, 'A1');

    $this->actingAs($owner)
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$unit->id], [
            'name' => 'Garbage',
            'kind' => 'fixed',
            'utility' => null,
            'rate' => null,
            'amounts' => [$unit->id => '500'],
        ]))
        ->assertRedirect();

    UtilityCharge::firstOrFail()->update(['is_active' => false]);

    expect(UtilityBilling::breakdownForLease($lease->fresh())['total'])->toBe('25000.00');
});

test('updating a charge reprices and reassigns units', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = utilityOrganization($owner);
    $property = utilityProperty($organization, $owner);
    [$unit] = [makeUnitWithLease($organization, $owner, $property, 'A1')[0]];
    $unitB = $property->units()->create(['name' => 'B2', 'status' => 'vacant', 'created_by' => $owner->id]);

    $this->actingAs($owner)
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$unit->id], [
            'name' => 'Garbage',
            'kind' => 'fixed',
            'utility' => null,
            'rate' => null,
            'amounts' => [$unit->id => '500'],
        ]))
        ->assertRedirect();

    $charge = UtilityCharge::firstOrFail();

    $this->actingAs($owner)
        ->put(utilityChargeUrl($organization, "/utilities/charges/{$charge->id}"), [
            'property_id' => $property->id,
            'name' => 'Garbage collection',
            'currency' => 'KES',
            'is_active' => true,
            'unit_ids' => [$unitB->id],
            'amounts' => [$unitB->id => '800'],
        ])
        ->assertRedirect();

    $charge->refresh();
    expect($charge->name)->toBe('Garbage collection')
        ->and($charge->units->pluck('id')->all())->toBe([$unitB->id])
        ->and($charge->units->first()->pivot->amount)->toEqual(800);
});

test('deleting a charge needs the current password', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = utilityOrganization($owner);
    $property = utilityProperty($organization, $owner);
    [$unit] = [makeUnitWithLease($organization, $owner, $property, 'A1')[0]];

    $this->actingAs($owner)
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$unit->id], [
            'name' => 'Garbage',
            'kind' => 'fixed',
            'utility' => null,
            'rate' => null,
            'amounts' => [$unit->id => '500'],
        ]))
        ->assertRedirect();

    $charge = UtilityCharge::firstOrFail();

    $this->actingAs($owner)
        ->delete(utilityChargeUrl($organization, "/utilities/charges/{$charge->id}"), [
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('password');

    expect(UtilityCharge::count())->toBe(1);

    $this->actingAs($owner)
        ->delete(utilityChargeUrl($organization, "/utilities/charges/{$charge->id}"), [
            'password' => 'password',
        ])
        ->assertRedirect();

    expect(UtilityCharge::count())->toBe(0);
});

test('a charge stores its GL code on create and update', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = utilityOrganization($owner);
    $property = utilityProperty($organization, $owner);
    [$unit] = [makeUnitWithLease($organization, $owner, $property, 'A1')[0]];

    $this->actingAs($owner)
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$unit->id], [
            'name' => 'Garbage',
            'kind' => 'fixed',
            'utility' => null,
            'rate' => null,
            'amounts' => [$unit->id => '500'],
            'gl_code' => '4100-utilities',
        ]))
        ->assertRedirect();

    $charge = UtilityCharge::firstOrFail();
    expect($charge->gl_code)->toBe('4100-utilities');

    $this->actingAs($owner)
        ->put(utilityChargeUrl($organization, "/utilities/charges/{$charge->id}"), [
            'name' => 'Garbage',
            'currency' => 'KES',
            'gl_code' => '4200-garbage',
            'is_active' => true,
            'unit_ids' => [$unit->id],
            'amounts' => [$unit->id => '500'],
        ])
        ->assertRedirect();

    expect($charge->refresh()->gl_code)->toBe('4200-garbage');
});

test('a shared charge splits the master bill equally and ties to the cent', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = utilityOrganization($owner);
    $property = utilityProperty($organization, $owner);
    [$unitA] = [makeUnitWithLease($organization, $owner, $property, 'A1')[0]];
    [$unitB, $leaseB] = makeUnitWithLease($organization, $owner, $property, 'B2');

    $this->actingAs($owner)
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$unitA->id, $unitB->id], [
            'name' => 'Water master',
            'kind' => 'shared',
            'utility' => null,
            'rate' => null,
            'allocation' => 'equal',
            'master_amount' => '100.00',
        ]))
        ->assertRedirect();

    $breakdown = UtilityBilling::breakdownForLease($leaseB->fresh());

    expect($breakdown['shared'])->toBe('50.00')
        ->and($breakdown['total'])->toBe('25050.00')
        ->and($breakdown['lines'])->toHaveCount(1)
        ->and($breakdown['lines'][0]['detail'])->toContain('100.00 KES')
        ->and($breakdown['lines'][0]['detail'])->toContain('2 units');
});

test('a shared charge splits by active leases per unit', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = utilityOrganization($owner);
    $property = utilityProperty($organization, $owner);
    [$unitA, $leaseA] = makeUnitWithLease($organization, $owner, $property, 'A1');
    [$unitB] = [makeUnitWithLease($organization, $owner, $property, 'B2')[0]];

    // A second active lease on B2: two billed parties against one on A1.
    $person = Person::create(['first_name' => 'John', 'email' => 'john-b2@x.test', 'status' => 'active']);
    Lease::create([
        'person_id' => $person->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $unitB->id,
        'starts_at' => '2026-01-01',
        'rent_amount' => 20000,
        'rent_frequency' => 'monthly',
        'currency' => 'KES',
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$unitA->id, $unitB->id], [
            'name' => 'Water master',
            'kind' => 'shared',
            'utility' => null,
            'rate' => null,
            'allocation' => 'occupants',
            'master_amount' => '300.00',
        ]))
        ->assertRedirect();

    expect(UtilityBilling::breakdownForLease($leaseA->fresh())['shared'])->toBe('100.00')
        ->and(UtilityBilling::totalsForLeases(collect([$leaseA->fresh()]))[$leaseA->id]['shared'])->toBe('100.00');
});

test('a shared charge needs a split basis and a master total', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = utilityOrganization($owner);
    $property = utilityProperty($organization, $owner);
    [$unit] = [makeUnitWithLease($organization, $owner, $property, 'A1')[0]];

    $this->actingAs($owner)
        ->from(utilityChargeUrl($organization, '/utilities'))
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$unit->id], [
            'kind' => 'shared',
            'utility' => null,
            'rate' => null,
            'allocation' => 'equal',
        ]))
        ->assertRedirect(utilityChargeUrl($organization, '/utilities'))
        ->assertSessionHas('inertia.flash_data', fn (array $flash) => str_contains(
            $flash['toast']['message'] ?? '',
            'master bill total'
        ));

    expect(UtilityCharge::count())->toBe(0);
});

test('the agreement utility_charges token lists active charges', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = utilityOrganization($owner);
    $property = utilityProperty($organization, $owner);
    [$unit, $lease] = makeUnitWithLease($organization, $owner, $property, 'A1');

    $this->actingAs($owner)
        ->post(utilityChargeUrl($organization, '/utilities/charges'), chargePayload($property, [$unit->id], [
            'name' => 'Garbage',
            'kind' => 'fixed',
            'utility' => null,
            'rate' => null,
            'amounts' => [$unit->id => '500'],
        ]))
        ->assertRedirect();

    $html = LeaseAgreementTemplate::render('<p>{{utility_charges}}</p>', $lease->fresh());

    expect($html)->toContain('Garbage')
        ->and($html)->toContain('500.00 KES per period')
        ->and($html)->not->toContain('{{utility_charges}}');
});
