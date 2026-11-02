<?php

use App\Models\DepositRefund;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
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
