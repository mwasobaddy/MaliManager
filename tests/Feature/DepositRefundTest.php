<?php

use App\Models\Delegation;
use App\Models\DepositRefund;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SubPermission;
use App\Models\SubRole;
use App\Models\Unit;
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

function refundGraph(): array
{
    $owner = User::factory()->create(['onboarded_at' => now()]);

    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Refund Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $property = $organization->properties()->create([
        'name' => 'Refund Court',
        'slug' => 'refund-court',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = Unit::factory()->create(['property_id' => $property->id]);

    $lease = Lease::create([
        'person_id' => Person::factory()->create()->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'deposit' => 50000,
        'rent_amount' => 25000,
        'rent_frequency' => 'monthly',
        'currency' => 'KES',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    return [$owner, $organization, $lease];
}

function refundUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->firstOrCreate(
        ['domain' => $organization->slug.'.refund.test'],
        ['domain' => $organization->slug.'.refund.test'],
    );

    return "http://{$domain->domain}{$path}";
}

test('an owner requests a refund and the amount is stored in cents', function () {
    [$owner, $organization, $lease] = refundGraph();

    $this->actingAs($owner)
        ->post(refundUrl($organization, '/deposit-refunds'), [
            'lease_id' => $lease->id,
            'amount' => '12500.75',
            'reason' => 'Occupant vacated; unit in good condition.',
        ])
        ->assertRedirect();

    $refund = DepositRefund::firstOrFail();

    expect($refund->amount_cents)->toBe(1250075)
        ->and($refund->status->value)->toBe('pending')
        ->and($refund->organization_id)->toBe($organization->id)
        ->and($refund->requested_by)->toBe($owner->id);
});

test('a refund larger than the deposit is rejected', function () {
    [$owner, $organization, $lease] = refundGraph();

    $this->actingAs($owner)
        ->post(refundUrl($organization, '/deposit-refunds'), [
            'lease_id' => $lease->id,
            'amount' => '60000',
            'reason' => 'Too much.',
        ])
        ->assertSessionHasErrors('amount');

    expect(DepositRefund::count())->toBe(0);
});

test('a lease cannot have two pending refunds', function () {
    [$owner, $organization, $lease] = refundGraph();

    DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 100000,
        'currency' => 'KES',
        'reason' => 'First.',
        'status' => 'pending',
        'requested_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(refundUrl($organization, '/deposit-refunds'), [
            'lease_id' => $lease->id,
            'amount' => '1',
            'reason' => 'Second.',
        ])
        ->assertSessionHasErrors('lease_id');

    expect(DepositRefund::count())->toBe(1);
});

test('an owner approves a pending refund', function () {
    [$owner, $organization, $lease] = refundGraph();

    $refund = DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 2500000,
        'currency' => 'KES',
        'reason' => 'Vacated.',
        'status' => 'pending',
        'requested_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->patch(refundUrl($organization, "/deposit-refunds/{$refund->id}"), [
            'decision' => 'approve',
            'note' => 'Paid by M-Pesa.',
        ])
        ->assertRedirect();

    $refund->refresh();

    expect($refund->status->value)->toBe('approved')
        ->and($refund->decided_by)->toBe($owner->id)
        ->and($refund->decided_at)->not->toBeNull()
        ->and($refund->decision_note)->toBe('Paid by M-Pesa.');
});

test('an already-decided refund cannot be decided again', function () {
    [$owner, $organization, $lease] = refundGraph();

    $refund = DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 2500000,
        'currency' => 'KES',
        'reason' => 'Vacated.',
        'status' => 'approved',
        'requested_by' => $owner->id,
        'decided_by' => $owner->id,
        'decided_at' => now(),
    ]);

    $this->actingAs($owner)
        ->patch(refundUrl($organization, "/deposit-refunds/{$refund->id}"), [
            'decision' => 'reject',
        ])
        ->assertSessionHasErrors('decision');

    expect($refund->fresh()->status->value)->toBe('approved');
});

test('the refunds page resolves the tenant name through the occupant person', function () {
    [$owner, $organization, $lease] = refundGraph();

    $person = Person::factory()->create(['first_name' => 'Tenant', 'last_name' => 'Person']);
    $occupant = Occupant::factory()->create([
        'organization_id' => $organization->id,
        'person_id' => $person->id,
    ]);
    $lease->update(['occupant_id' => $occupant->id]);

    DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 1250000,
        'currency' => 'KES',
        'reason' => 'Vacated.',
        'status' => 'pending',
        'requested_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(refundUrl($organization, '/deposit-refunds'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/deposit-refunds/index')
            ->where('refunds.data.0.tenant_name', 'Tenant Person')
            ->where('leases.0.label', 'Tenant Person')
        );
});
function refundStaff(Organization $organization, array $keys): User
{
    $subRole = SubRole::factory()->create(['organization_id' => $organization->id]);
    $subRole->subPermissions()->sync(SubPermission::whereIn('key', $keys)->pluck('id'));

    $staff = User::factory()->create(['onboarded_at' => now()]);
    $organization->users()->attach($staff->id, ['is_owner' => false, 'sub_role_id' => $subRole->id, 'status' => 'active']);

    return $staff;
}

function refundLease(Organization $organization, User $owner, string $propertyName, string $slug): Lease
{
    $property = $organization->properties()->create([
        'name' => $propertyName,
        'slug' => $slug,
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
    $unit = Unit::factory()->create(['property_id' => $property->id]);

    return Lease::create([
        'person_id' => Person::factory()->create()->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'deposit' => 50000,
        'rent_amount' => 25000,
        'rent_frequency' => 'monthly',
        'currency' => 'KES',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

/**
 * @return array{0: User, 1: Organization}
 */
function refundTwoPropertyGraph(): array
{
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Refund Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    refundLease($organization, $owner, 'Court A', 'court-a');
    refundLease($organization, $owner, 'Court B', 'court-b');

    return [$owner, $organization];
}

function delegateToProperty(Organization $organization, User $owner, User $staff, int $propertyId): void
{
    $membership = OrganizationUser::where('organization_id', $organization->id)
        ->where('user_id', $staff->id)
        ->firstOrFail();

    Delegation::create([
        'organization_id' => $organization->id,
        'organization_user_id' => $membership->id,
        'delegatable_type' => Property::class,
        'delegatable_id' => $propertyId,
        'created_by' => $owner->id,
    ]);
}

test('delegated staff only see refunds on their properties', function () {
    [$owner, $organization] = refundTwoPropertyGraph();

    $leaseA = Lease::where('organization_id', $organization->id)->whereHas('property', fn ($q) => $q->where('slug', 'court-a'))->firstOrFail();
    $leaseB = Lease::where('organization_id', $organization->id)->whereHas('property', fn ($q) => $q->where('slug', 'court-b'))->firstOrFail();

    foreach ([$leaseA, $leaseB] as $lease) {
        DepositRefund::create([
            'organization_id' => $organization->id,
            'lease_id' => $lease->id,
            'amount_cents' => 1250000,
            'currency' => 'KES',
            'reason' => 'Vacated.',
            'status' => 'pending',
            'requested_by' => $owner->id,
        ]);
    }

    $staff = refundStaff($organization, ['lease.manage', 'lease.edit']);
    delegateToProperty($organization, $owner, $staff, $leaseA->property_id);

    $this->actingAs($staff)
        ->get(refundUrl($organization, '/deposit-refunds'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('refunds.data', 1)
            ->where('refunds.data.0.lease_id', $leaseA->id)
            ->has('leases', 1)
            ->where('leases.0.value', $leaseA->id)
        );
});

test('staff cannot request or decide refunds outside their delegation', function () {
    [$owner, $organization] = refundTwoPropertyGraph();

    $leaseA = Lease::where('organization_id', $organization->id)->whereHas('property', fn ($q) => $q->where('slug', 'court-a'))->firstOrFail();
    $leaseB = Lease::where('organization_id', $organization->id)->whereHas('property', fn ($q) => $q->where('slug', 'court-b'))->firstOrFail();

    $staff = refundStaff($organization, ['lease.manage', 'lease.edit']);
    delegateToProperty($organization, $owner, $staff, $leaseA->property_id);

    $refund = DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $leaseB->id,
        'amount_cents' => 1250000,
        'currency' => 'KES',
        'reason' => 'Vacated.',
        'status' => 'pending',
        'requested_by' => $owner->id,
    ]);

    // Deciding the other property's refund bounces and changes nothing.
    $this->actingAs($staff)
        ->patch(refundUrl($organization, "/deposit-refunds/{$refund->id}"), [
            'decision' => 'approve',
            'amount' => 12500,
        ])
        ->assertRedirect();

    expect($refund->fresh()->status->value)->toBe('pending');

    // Requesting against the other property's lease bounces without writing.
    $this->actingAs($staff)
        ->post(refundUrl($organization, '/deposit-refunds'), [
            'lease_id' => $leaseB->id,
            'amount' => 12500,
            'reason' => 'Trying.',
        ])
        ->assertRedirect();

    expect(DepositRefund::count())->toBe(1);
});

test('the refunds page lists refunds with their status', function () {
    [$owner, $organization, $lease] = refundGraph();

    DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 1250000,
        'currency' => 'KES',
        'reason' => 'Vacated.',
        'status' => 'pending',
        'requested_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(refundUrl($organization, '/deposit-refunds'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/deposit-refunds/index')
            ->has('refunds.data', 1)
            ->where('refunds.data.0.status', 'pending')
            ->where('refunds.data.0.amount_cents', 1250000)
            ->has('leases', 1)
        );
});
