<?php

use App\Mail\OperationalNoticeMail;
use App\Models\Delegation;
use App\Models\DepositRefund;
use App\Models\Lease;
use App\Models\MoveOutNotice;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

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
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
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
        'rent_amount' => 25000, 'rent_frequency' => 'monthly',
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

    $staff = refundStaff($organization, ['lease.manage', 'deposit.manage']);
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

test('an approved refund can be marked paid with a reference', function () {
    [$owner, $organization, $lease] = refundGraph();

    $refund = DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 1250000,
        'currency' => 'KES',
        'reason' => 'Vacated.',
        'status' => 'approved',
        'requested_by' => $owner->id,
        'decided_by' => $owner->id,
        'decided_at' => now(),
    ]);

    $this->actingAs($owner)
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/pay"), [
            'reference' => 'M-Pesa CODE123',
        ])
        ->assertRedirect();

    expect($refund->fresh()->status->value)->toBe('paid')
        ->and($refund->fresh()->paid_by)->toBe($owner->id)
        ->and($refund->fresh()->paid_reference)->toBe('M-Pesa CODE123')
        ->and($refund->fresh()->paid_at)->not->toBeNull();
});

test('only an approved refund can be marked paid', function () {
    [$owner, $organization, $lease] = refundGraph();

    $refund = DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 1250000,
        'currency' => 'KES',
        'reason' => 'Vacated.',
        'status' => 'pending',
        'requested_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->from(refundUrl($organization, '/deposit-refunds'))
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/pay"), [
            'reference' => 'M-Pesa CODE123',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('reference');

    expect($refund->fresh()->status->value)->toBe('pending');
});

test('a paid refund cannot be marked paid twice', function () {
    [$owner, $organization, $lease] = refundGraph();

    $refund = DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 1250000,
        'currency' => 'KES',
        'reason' => 'Vacated.',
        'status' => 'paid',
        'requested_by' => $owner->id,
        'decided_by' => $owner->id,
        'decided_at' => now(),
        'paid_by' => $owner->id,
        'paid_at' => now(),
        'paid_reference' => 'M-Pesa FIRST',
    ]);

    $this->actingAs($owner)
        ->from(refundUrl($organization, '/deposit-refunds'))
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/pay"), [
            'reference' => 'M-Pesa SECOND',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('reference');

    expect($refund->fresh()->paid_reference)->toBe('M-Pesa FIRST');
});

test('a caretaker can request but cannot decide or pay', function () {
    [$owner, $organization] = refundTwoPropertyGraph();

    $lease = Lease::where('organization_id', $organization->id)->whereHas('property', fn ($q) => $q->where('slug', 'court-a'))->firstOrFail();
    $staff = refundStaff($organization, ['lease.manage']);
    delegateToProperty($organization, $owner, $staff, $lease->property_id);

    // Requesting works with the caretaker key.
    $this->actingAs($staff)
        ->post(refundUrl($organization, '/deposit-refunds'), [
            'lease_id' => $lease->id,
            'amount' => 12500,
            'reason' => 'Vacated, unit clean.',
        ])
        ->assertRedirect();

    $refund = DepositRefund::where('organization_id', $organization->id)->firstOrFail();

    // Deciding needs the deposits key.
    $this->actingAs($staff)
        ->patch(refundUrl($organization, "/deposit-refunds/{$refund->id}"), [
            'decision' => 'approve',
            'note' => 'Looks good.',
        ])
        ->assertRedirect();
    assertErrorToast();
    expect($refund->fresh()->status->value)->toBe('pending');

    // The landlord grants deposit rights; the same staffer can now decide.
    $role = $staff->organizations()->where('organization_id', $organization->id)->first()->pivot->sub_role_id;
    SubRole::find($role)->subPermissions()->attach(
        SubPermission::where('key', 'deposit.manage')->pluck('id')->all()
    );

    $this->actingAs($staff)
        ->patch(refundUrl($organization, "/deposit-refunds/{$refund->id}"), [
            'decision' => 'approve',
            'note' => 'Looks good.',
        ])
        ->assertRedirect();

    expect($refund->fresh()->status->value)->toBe('approved');

    // ...and record the payout.
    $this->actingAs($staff)
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/pay"), [
            'reference' => 'M-Pesa CARETAKER1',
        ])
        ->assertRedirect();

    expect($refund->fresh()->status->value)->toBe('paid')
        ->and($refund->fresh()->paid_by)->toBe($staff->id);
});

test('ended leases holding a deposit are requestable', function () {
    [$owner, $organization, $lease] = refundGraph();
    $lease->update(['status' => 'ended']);

    $this->actingAs($owner)
        ->get(refundUrl($organization, '/deposit-refunds'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('leases', 1)
            ->where('leases.0.value', $lease->id)
        );
});

test('the refunds index ships tenant, unit and property names', function () {
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
        ->get(refundUrl($organization, '/deposit-refunds?search=Tenant'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organisation/deposit-refunds/index')
            ->where('refunds.total', 1)
            ->where('refunds.data.0.tenant_name', 'Tenant Person')
            ->where('refunds.data.0.unit_name', $lease->unit->name)
            ->where('refunds.data.0.property_name', $lease->property->name)
        );

    $this->actingAs($owner)
        ->get(refundUrl($organization, '/deposit-refunds?search=Nobody'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('refunds.total', 0)
        );
});

function refundNotice(array $overrides = []): array
{
    [$owner, $organization, $lease] = refundGraph();

    $notice = MoveOutNotice::create(array_merge([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'requested_by' => $owner->id,
        'move_out_date' => now()->addDays(40)->toDateString(),
        'status' => 'pending',
    ], $overrides));

    return [$owner, $organization, $lease, $notice];
}

test('acknowledging a notice auto-creates an awaiting-inspection refund', function () {
    [$owner, $organization, $lease, $notice] = refundNotice();

    $this->actingAs($owner)
        ->post(refundUrl($organization, "/move-out-notices/{$notice->id}/acknowledge"))
        ->assertRedirect();

    $refund = DepositRefund::firstOrFail();

    expect($refund->status->value)->toBe('awaiting_inspection')
        ->and($refund->amount_cents)->toBe(5000000)
        ->and($refund->requested_by)->toBeNull();
});

test('acknowledging twice does not duplicate the refund', function () {
    [$owner, $organization, $lease, $notice] = refundNotice();

    $this->actingAs($owner)
        ->post(refundUrl($organization, "/move-out-notices/{$notice->id}/acknowledge"))
        ->assertRedirect();

    // Second acknowledge finds no pending notice left to act on.
    $this->actingAs($owner)
        ->from(refundUrl($organization, '/deposit-refunds'))
        ->post(refundUrl($organization, "/move-out-notices/{$notice->id}/acknowledge"))
        ->assertRedirect();

    expect(DepositRefund::count())->toBe(1);
});

test('ending a lease auto-requests the deposit refund', function () {
    [$owner, $organization, $lease] = refundGraph();

    $this->actingAs($owner)
        ->post(refundUrl($organization, "/{$lease->property->slug}/leases/{$lease->id}/end"), [
            'password' => 'password',
        ])
        ->assertRedirect();

    $refund = DepositRefund::firstOrFail();

    expect($refund->status->value)->toBe('awaiting_inspection')
        ->and($refund->amount_cents)->toBe(5000000);
});

test('ending a lease with no deposit files nothing', function () {
    [$owner, $organization, $lease] = refundGraph();
    $lease->update(['deposit' => 0]);

    $this->actingAs($owner)
        ->post(refundUrl($organization, "/{$lease->property->slug}/leases/{$lease->id}/end"), [
            'password' => 'password',
        ])
        ->assertRedirect();

    expect(DepositRefund::count())->toBe(0);
});

test('an inspector adds photo-backed deductions and submits for review', function () {
    [$owner, $organization, $lease] = refundGraph();

    $refund = DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 2500000,
        'currency' => 'KES',
        'reason' => 'Vacated.',
        'status' => 'awaiting_inspection',
        'requested_by' => $owner->id,
    ]);

    $staff = refundStaff($organization, ['lease.manage']);
    delegateToProperty($organization, $owner, $staff, $lease->property_id);

    $this->actingAs($staff)
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/deductions"), [
            'description' => 'Broken window pane',
            'amount' => '3500',
            'photos' => [UploadedFile::fake()->image('damage.jpg')],
        ])
        ->assertRedirect();

    expect($refund->deductions()->count())->toBe(1);

    $this->actingAs($staff)
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/submit"))
        ->assertRedirect();

    expect($refund->fresh()->status->value)->toBe('in_review');
});

test('deductions require photo proof', function () {
    [$owner, $organization, $lease] = refundGraph();

    $refund = DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 2500000,
        'currency' => 'KES',
        'reason' => 'Vacated.',
        'status' => 'awaiting_inspection',
        'requested_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->from(refundUrl($organization, '/deposit-refunds'))
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/deductions"), [
            'description' => 'Broken window pane',
            'amount' => '3500',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('photos');

    expect($refund->deductions()->count())->toBe(0);
});

test('a reviewer approves deductions and the refund moves to pending', function () {
    [$owner, $organization, $lease] = refundGraph();

    $refund = DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 2500000,
        'currency' => 'KES',
        'reason' => 'Vacated.',
        'status' => 'in_review',
        'requested_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/review"), [
            'decision' => 'approve',
            'note' => 'Photos check out.',
        ])
        ->assertRedirect();

    $refund = $refund->fresh();

    expect($refund->status->value)->toBe('pending')
        ->and($refund->reviewed_by)->toBe($owner->id)
        ->and($refund->review_note)->toBe('Photos check out.');
});

test('a reviewer can send deductions back with a reason', function () {
    [$owner, $organization, $lease] = refundGraph();

    $refund = DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 2500000,
        'currency' => 'KES',
        'reason' => 'Vacated.',
        'status' => 'in_review',
        'requested_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/review"), [
            'decision' => 'reject',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('note');

    $this->actingAs($owner)
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/review"), [
            'decision' => 'reject',
            'note' => 'Need a photo of the second bedroom too.',
        ])
        ->assertRedirect();

    expect($refund->fresh()->status->value)->toBe('awaiting_inspection');
});

test('an inspector cannot approve deductions or decide the refund', function () {
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

    $staff = refundStaff($organization, ['lease.manage']);
    delegateToProperty($organization, $owner, $staff, $lease->property_id);

    $this->actingAs($staff)
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/review"), [
            'decision' => 'approve',
        ])
        ->assertRedirect();
    assertErrorToast();

    $this->actingAs($staff)
        ->patch(refundUrl($organization, "/deposit-refunds/{$refund->id}"), [
            'decision' => 'approve',
        ])
        ->assertRedirect();
    assertErrorToast();

    expect($refund->fresh()->status->value)->toBe('pending');
});

test('a money holder without the review key cannot approve deductions', function () {
    [$owner, $organization, $lease] = refundGraph();

    $refund = DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 2500000,
        'currency' => 'KES',
        'reason' => 'Vacated.',
        'status' => 'in_review',
        'requested_by' => $owner->id,
    ]);

    $staff = refundStaff($organization, ['lease.manage', 'deposit.manage']);
    delegateToProperty($organization, $owner, $staff, $lease->property_id);

    $this->actingAs($staff)
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/review"), [
            'decision' => 'approve',
        ])
        ->assertRedirect();
    assertErrorToast();

    expect($refund->fresh()->status->value)->toBe('in_review');
});

test('a reviewer can approve deductions but cannot decide the payout', function () {
    [$owner, $organization, $lease] = refundGraph();

    $refund = DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 2500000,
        'currency' => 'KES',
        'reason' => 'Vacated.',
        'status' => 'in_review',
        'requested_by' => $owner->id,
    ]);

    $staff = refundStaff($organization, ['lease.manage', 'deposit.review']);
    delegateToProperty($organization, $owner, $staff, $lease->property_id);

    $this->actingAs($staff)
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/review"), [
            'decision' => 'approve',
            'note' => 'Looks good.',
        ])
        ->assertRedirect();

    expect($refund->fresh()->status->value)->toBe('pending');

    $this->actingAs($staff)
        ->patch(refundUrl($organization, "/deposit-refunds/{$refund->id}"), [
            'decision' => 'approve',
        ])
        ->assertRedirect();
    assertErrorToast();

    expect($refund->fresh()->status->value)->toBe('pending');
});

test('a pending refund cannot be paid before approval', function () {
    [$owner, $organization, $lease] = refundGraph();

    $refund = DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 2500000,
        'currency' => 'KES',
        'reason' => 'Vacated.',
        'status' => 'in_review',
        'requested_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->from(refundUrl($organization, '/deposit-refunds'))
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/pay"))
        ->assertRedirect()
        ->assertSessionHasErrors('reference');

    expect($refund->fresh()->status->value)->toBe('in_review');
});

test('owners and tenant are notified through the full inspection flow', function () {
    Mail::fake();

    [$owner, $organization, $lease, $notice] = refundNotice();

    $this->actingAs($owner)
        ->post(refundUrl($organization, "/move-out-notices/{$notice->id}/acknowledge"))
        ->assertRedirect();

    $refund = DepositRefund::firstOrFail();
    expect($refund->status->value)->toBe('awaiting_inspection');

    $this->actingAs($owner)
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/submit"))
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/review"), [
            'decision' => 'approve',
            'note' => 'Photos check out.',
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->patch(refundUrl($organization, "/deposit-refunds/{$refund->id}"), [
            'decision' => 'approve',
            'note' => 'Pay the balance.',
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(refundUrl($organization, "/deposit-refunds/{$refund->id}/pay"), [
            'reference' => 'M-Pesa X',
        ])
        ->assertRedirect();

    expect($refund->fresh()->status->value)->toBe('paid');

    Mail::assertSent(OperationalNoticeMail::class, 6);
});
