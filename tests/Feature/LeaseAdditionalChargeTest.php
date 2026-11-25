<?php

use App\Models\Lease;
use App\Models\LeaseAdditionalCharge;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Property;
use App\Models\User;
use App\Services\TenantService;
use App\Support\UtilityBilling;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function extrasOrganization(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Extras Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function extrasProperty(Organization $organization, User $owner): Property
{
    return $organization->properties()->create([
        'name' => 'Extra Court',
        'slug' => 'extra-court',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function extrasUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

function extrasLeaseUrl(Property $property, Lease $lease, string $suffix = ''): string
{
    return "/{$property->slug}/leases/{$lease->id}/additional-charges{$suffix}";
}

function makeExtrasLease(Organization $organization, User $owner, Property $property, string $name): array
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

test('a fixed extra is stored on the lease', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = extrasOrganization($owner);
    $property = extrasProperty($organization, $owner);
    [, $lease] = makeExtrasLease($organization, $owner, $property, 'A1');

    $this->actingAs($owner)
        ->post(extrasUrl($organization, extrasLeaseUrl($property, $lease)), [
            'name' => 'Parking',
            'kind' => 'fixed',
            'amount' => '2000',
            'currency' => 'KES',
        ])
        ->assertRedirect();

    $extra = LeaseAdditionalCharge::firstOrFail();
    expect($extra->lease_id)->toBe($lease->id)
        ->and($extra->kind)->toBe('fixed')
        ->and((float) $extra->amount)->toBe(2000.0)
        ->and($extra->is_active)->toBeTrue();
});

test('a one-time extra stores its GL code', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = extrasOrganization($owner);
    $property = extrasProperty($organization, $owner);
    [, $lease] = makeExtrasLease($organization, $owner, $property, 'A1');

    $this->actingAs($owner)
        ->post(extrasUrl($organization, extrasLeaseUrl($property, $lease)), [
            'name' => 'Move-in cleaning',
            'kind' => 'one_time',
            'amount' => '1500',
            'currency' => 'KES',
            'gl_code' => '4200-cleaning',
        ])
        ->assertRedirect();

    expect(LeaseAdditionalCharge::firstOrFail()->gl_code)->toBe('4200-cleaning');
});

test('extras require a name, kind and amount', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = extrasOrganization($owner);
    $property = extrasProperty($organization, $owner);
    [, $lease] = makeExtrasLease($organization, $owner, $property, 'A1');

    $this->actingAs($owner)
        ->post(extrasUrl($organization, extrasLeaseUrl($property, $lease)), [
            'name' => '',
            'kind' => 'bogus',
            'amount' => 'not-a-number',
        ])
        ->assertSessionHasErrors(['name', 'kind', 'amount']);

    expect(LeaseAdditionalCharge::count())->toBe(0);
});

test('the lease total adds fixed and pending one-time extras to rent', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = extrasOrganization($owner);
    $property = extrasProperty($organization, $owner);
    [, $lease] = makeExtrasLease($organization, $owner, $property, 'A1');

    foreach ([
        ['Parking', 'fixed', '2000'],
        ['Move-in cleaning', 'one_time', '1500'],
    ] as [$name, $kind, $amount]) {
        $this->actingAs($owner)
            ->post(extrasUrl($organization, extrasLeaseUrl($property, $lease)), [
                'name' => $name,
                'kind' => $kind,
                'amount' => $amount,
                'currency' => 'KES',
            ])
            ->assertRedirect();
    }

    $breakdown = UtilityBilling::breakdownForLease($lease->fresh());

    expect($breakdown['extras'])->toBe('3500.00')
        ->and($breakdown['total'])->toBe('28500.00')
        ->and($breakdown['extra_lines'])->toHaveCount(2);
});

test('consumed one-time and inactive extras are excluded from the total', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = extrasOrganization($owner);
    $property = extrasProperty($organization, $owner);
    [, $lease] = makeExtrasLease($organization, $owner, $property, 'A1');

    LeaseAdditionalCharge::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'name' => 'Spent cleaning',
        'kind' => 'one_time',
        'amount' => '1500',
        'currency' => 'KES',
        'consumed_at' => now(),
        'created_by' => $owner->id,
    ]);
    LeaseAdditionalCharge::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'name' => 'Paused parking',
        'kind' => 'fixed',
        'amount' => '2000',
        'currency' => 'KES',
        'is_active' => false,
        'created_by' => $owner->id,
    ]);

    $breakdown = UtilityBilling::breakdownForLease($lease->fresh());

    expect($breakdown['extras'])->toBe('0.00')
        ->and($breakdown['extra_lines'])->toBeEmpty()
        ->and($breakdown['total'])->toBe('25000.00');
});

test('extras stay on their own lease', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = extrasOrganization($owner);
    $property = extrasProperty($organization, $owner);
    [, $leaseA] = makeExtrasLease($organization, $owner, $property, 'A1');
    [, $leaseB] = makeExtrasLease($organization, $owner, $property, 'B2');

    $this->actingAs($owner)
        ->post(extrasUrl($organization, extrasLeaseUrl($property, $leaseA)), [
            'name' => 'Parking',
            'kind' => 'fixed',
            'amount' => '2000',
            'currency' => 'KES',
        ])
        ->assertRedirect();

    expect(UtilityBilling::breakdownForLease($leaseA->fresh())['extras'])->toBe('2000.00')
        ->and(UtilityBilling::breakdownForLease($leaseB->fresh())['extras'])->toBe('0.00')
        ->and(UtilityBilling::totalsForLeases(collect([$leaseA->fresh(), $leaseB->fresh()]))[$leaseB->id]['extras'])->toBe('0.00');
});

test('updating an extra reprices it', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = extrasOrganization($owner);
    $property = extrasProperty($organization, $owner);
    [, $lease] = makeExtrasLease($organization, $owner, $property, 'A1');

    $this->actingAs($owner)
        ->post(extrasUrl($organization, extrasLeaseUrl($property, $lease)), [
            'name' => 'Parking',
            'kind' => 'fixed',
            'amount' => '2000',
            'currency' => 'KES',
        ])
        ->assertRedirect();

    $extra = LeaseAdditionalCharge::firstOrFail();

    $this->actingAs($owner)
        ->put(extrasUrl($organization, extrasLeaseUrl($property, $lease, "/{$extra->id}")), [
            'name' => 'Covered parking',
            'amount' => '2500',
            'currency' => 'KES',
            'is_active' => true,
        ])
        ->assertRedirect();

    $extra->refresh();
    expect($extra->name)->toBe('Covered parking')
        ->and((float) $extra->amount)->toBe(2500.0);
});

test('deleting an extra needs the current password', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = extrasOrganization($owner);
    $property = extrasProperty($organization, $owner);
    [, $lease] = makeExtrasLease($organization, $owner, $property, 'A1');

    $this->actingAs($owner)
        ->post(extrasUrl($organization, extrasLeaseUrl($property, $lease)), [
            'name' => 'Parking',
            'kind' => 'fixed',
            'amount' => '2000',
            'currency' => 'KES',
        ])
        ->assertRedirect();

    $extra = LeaseAdditionalCharge::firstOrFail();

    $this->actingAs($owner)
        ->delete(extrasUrl($organization, extrasLeaseUrl($property, $lease, "/{$extra->id}")), [
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('password');

    expect(LeaseAdditionalCharge::count())->toBe(1);

    $this->actingAs($owner)
        ->delete(extrasUrl($organization, extrasLeaseUrl($property, $lease, "/{$extra->id}")), [
            'password' => 'password',
        ])
        ->assertRedirect();

    expect(LeaseAdditionalCharge::count())->toBe(0);
});
