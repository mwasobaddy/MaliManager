<?php

use App\Actions\Deposits\DecideDepositRefund;
use App\Actions\Deposits\RequestDepositRefund;
use App\Data\Deposits\DecideDepositRefundData;
use App\Data\Deposits\RequestDepositRefundData;
use App\Enums\DepositRefundStatus;
use App\Models\Lease;
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

function actionRefundGraph(): array
{
    $owner = User::factory()->create(['onboarded_at' => now()]);

    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Action Refund Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $property = $organization->properties()->create([
        'name' => 'Action Refund Court',
        'slug' => 'action-refund-court',
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
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    return [$owner, $organization, $lease];
}

test('requesting a refund opens it in pending', function () {
    [$owner, $organization, $lease] = actionRefundGraph();

    $refund = app(RequestDepositRefund::class)->handle(
        $owner,
        new RequestDepositRefundData(
            organizationId: $organization->id,
            leaseId: $lease->id,
            amountCents: 100000,
            reason: 'Moved out.',
        ),
    );

    expect($refund->status)->toBe(DepositRefundStatus::Pending)
        ->and($refund->amount_cents)->toBe(100000)
        ->and($refund->requested_by)->toBe($owner->id);
});

test('deciding a refund records the decider and timestamp', function () {
    [$owner, $organization, $lease] = actionRefundGraph();

    $refund = app(RequestDepositRefund::class)->handle(
        $owner,
        new RequestDepositRefundData(
            organizationId: $organization->id,
            leaseId: $lease->id,
            amountCents: 100000,
            reason: 'Moved out.',
        ),
    );

    $decided = app(DecideDepositRefund::class)->handle(
        $refund,
        $owner,
        new DecideDepositRefundData(approve: true, note: 'Approved.'),
    );

    expect($decided->status)->toBe(DepositRefundStatus::Approved)
        ->and($decided->decided_by)->toBe($owner->id)
        ->and($decided->decided_at)->not->toBeNull();
});

test('deciding an already-decided refund leaves it untouched', function () {
    [$owner, $organization, $lease] = actionRefundGraph();

    $refund = app(RequestDepositRefund::class)->handle(
        $owner,
        new RequestDepositRefundData(
            organizationId: $organization->id,
            leaseId: $lease->id,
            amountCents: 100000,
            reason: 'Moved out.',
        ),
    );

    app(DecideDepositRefund::class)->handle(
        $refund,
        $owner,
        new DecideDepositRefundData(approve: false, note: 'No.'),
    );

    $again = app(DecideDepositRefund::class)->handle(
        $refund->fresh(),
        $owner,
        new DecideDepositRefundData(approve: true),
    );

    expect($again->status)->toBe(DepositRefundStatus::Rejected)
        ->and($again->decision_note)->toBe('No.');
});
