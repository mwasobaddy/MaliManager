<?php

use App\Enums\DepositRefundStatus;
use App\Enums\MoveOutNoticeStatus;
use App\Models\DepositRefund;
use App\Models\Lease;
use App\Models\MoveOutNotice;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\OccupantService;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function moveOutOrganization(User $owner): Organization
{
    return app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Moveout Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );
}

function moveOutProperty(Organization $organization, User $owner): Property
{
    return $organization->properties()->create([
        'name' => 'Moveout Court',
        'slug' => 'moveout-court',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);
}

function moveOutUrl(Organization $organization, string $path): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

/**
 * @return array{Occupant, Lease}
 */
function moveOutSetup(Organization $organization, User $owner, Property $property, string $unitName, array $leaseData = []): array
{
    $unit = $property->units()->create(['name' => $unitName, 'status' => 'vacant', 'created_by' => $owner->id]);
    $occupant = app(OccupantService::class)->create($organization, $property, $owner, [
        'first_name' => 'Marty',
        'last_name' => "Out-{$unitName}",
        'email' => "marty-out-{$unitName}@x.test",
        'status' => 'active',
        'unit_ids' => [$unit->id],
        'lease' => array_merge([
            'starts_at' => '2026-01-01',
            'rent_amount' => '10000',
            'rent_frequency' => 'monthly',
            'currency' => 'KES',
            'deposit' => '20000',
        ], $leaseData),
    ]);

    return [$occupant, Lease::where('unit_id', $unit->id)->firstOrFail()];
}

test('moving out requires the current password', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = moveOutOrganization($owner);
    $property = moveOutProperty($organization, $owner);
    [$occupant] = moveOutSetup($organization, $owner, $property, 'A1');
    $url = moveOutUrl($organization, "/{$property->slug}/occupants/{$occupant->id}/move-out");

    $this->actingAs($owner)
        ->from(moveOutUrl($organization, "/{$property->slug}/occupants"))
        ->post($url, ['password' => 'wrong-pass'])
        ->assertRedirect()
        ->assertSessionHasErrors('password');

    expect($occupant->fresh()->status)->toBe('active');

    $this->actingAs($owner)
        ->post($url, ['password' => 'password'])
        ->assertRedirect();

    expect($occupant->fresh()->status)->toBe('moved_out');
});

test('moving out ends that row’s lease and auto-files its deposit refund', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = moveOutOrganization($owner);
    $property = moveOutProperty($organization, $owner);

    $unitA = $property->units()->create(['name' => 'A1', 'status' => 'vacant', 'created_by' => $owner->id]);
    $unitB = $property->units()->create(['name' => 'B2', 'status' => 'vacant', 'created_by' => $owner->id]);
    $occupant = app(OccupantService::class)->create($organization, $property, $owner, [
        'first_name' => 'Marty',
        'last_name' => 'Multi',
        'email' => 'marty-multi@x.test',
        'status' => 'active',
        'unit_ids' => [$unitA->id, $unitB->id],
        'lease' => ['starts_at' => '2026-01-01', 'rent_amount' => '10000', 'rent_frequency' => 'monthly', 'currency' => 'KES', 'deposit' => '20000'],
    ]);
    $rowA = Occupant::where('organization_id', $organization->id)->where('person_id', $occupant->person_id)->orderBy('id')->firstOrFail();
    $rowB = Occupant::where('organization_id', $organization->id)->where('person_id', $occupant->person_id)->orderBy('id', 'desc')->firstOrFail();
    $leaseA = Lease::where('unit_id', $unitA->id)->firstOrFail();
    $leaseB = Lease::where('unit_id', $unitB->id)->firstOrFail();
    // Only the first tenancy holds a deposit.
    $leaseB->fill(['deposit' => '0'])->save();

    // Moving out row A ends only A’s lease; the sibling row is untouched.
    $this->actingAs($owner)
        ->post(moveOutUrl($organization, "/{$property->slug}/occupants/{$rowA->id}/move-out"), ['password' => 'password'])
        ->assertRedirect();

    expect($leaseA->fresh()->status)->toBe('ended')
        ->and($leaseB->fresh()->status)->toBe('active')
        ->and($rowA->fresh()->status)->toBe('moved_out')
        ->and($rowB->fresh()->status)->toBe('active');

    // The deposit-holding lease files awaiting-inspection.
    $refund = DepositRefund::where('lease_id', $leaseA->id)->first();
    expect($refund)->not->toBeNull()
        ->and($refund->status)->toBe(DepositRefundStatus::AwaitingInspection)
        ->and((float) ($refund->amount_cents / 100))->toBe(20000.0);

    // Moving out row B ends it too, but its zero deposit files nothing.
    $this->actingAs($owner)
        ->post(moveOutUrl($organization, "/{$property->slug}/occupants/{$rowB->id}/move-out"), ['password' => 'password'])
        ->assertRedirect();

    expect($leaseB->fresh()->status)->toBe('ended')
        ->and(DepositRefund::where('lease_id', $leaseB->id)->exists())->toBeFalse();
});

test('moving out never duplicates an in-flight refund', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = moveOutOrganization($owner);
    $property = moveOutProperty($organization, $owner);
    [$occupant, $lease] = moveOutSetup($organization, $owner, $property, 'A1');

    DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 2000000,
        'currency' => 'KES',
        'reason' => 'Manual request.',
        'status' => DepositRefundStatus::AwaitingInspection,
    ]);

    $this->actingAs($owner)
        ->post(moveOutUrl($organization, "/{$property->slug}/occupants/{$occupant->id}/move-out"), ['password' => 'password'])
        ->assertRedirect();

    expect(DepositRefund::where('lease_id', $lease->id)->count())->toBe(1);
});

test('the view sheet carries the occupant history newest first', function () {
    $owner = User::factory()->create(['onboarded_at' => now()]);
    $organization = moveOutOrganization($owner);
    $property = moveOutProperty($organization, $owner);
    [$occupant, $lease] = moveOutSetup($organization, $owner, $property, 'A1');

    Payment::create([
        'tenant_id' => $organization->tenant_id,
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'occupant_id' => $occupant->id,
        'amount' => '10000.00',
        'currency' => 'KES',
        'paid_on' => '2026-02-01',
        'method' => 'M-Pesa',
        'status' => 'received',
        'type' => 'rent',
        'created_by' => $owner->id,
    ]);

    MoveOutNotice::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'move_out_date' => '2026-03-01',
        'note' => 'Leaving town.',
        'status' => MoveOutNoticeStatus::Acknowledged,
        'requested_by' => $owner->id,
    ]);

    $lease->fill(['status' => 'ended', 'ends_at' => '2026-03-01'])->save();
    DepositRefund::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'amount_cents' => 2000000,
        'currency' => 'KES',
        'reason' => 'Ended.',
        'status' => DepositRefundStatus::AwaitingInspection,
    ]);

    $this->actingAs($owner)
        ->get(moveOutUrl($organization, "/{$property->slug}/occupants?mode=view&occupant={$occupant->id}"))
        ->assertOk()
        ->assertInertia(function ($page) use ($property) {
            $page->where('focusedOccupant.history', function ($history) {
                $rows = $history instanceof Collection ? $history->all() : (array) $history;
                $kinds = array_column($rows, 'kind');

                return in_array('lease_started', $kinds, true)
                    && in_array('lease_ended', $kinds, true)
                    && in_array('payment', $kinds, true)
                    && in_array('refund', $kinds, true)
                    && in_array('move_out_notice', $kinds, true);
            });

            $rows = collect($page->toArray()['props']['focusedOccupant']['history'] ?? [])->values();
            $kinds = $rows->pluck('kind')->all();

            foreach (['lease_started', 'lease_ended', 'payment', 'refund', 'move_out_notice'] as $kind) {
                expect($kinds)->toContain($kind);
            }

            // Every event is tagged with its tenancy for the sheet filter.
            $unitId = Unit::where('property_id', $property->id)->where('name', 'A1')->value('id');
            expect($rows->pluck('unit_id')->unique()->all())->toBe([$unitId])
                ->and($rows->pluck('unit_name')->unique()->all())->toBe(['A1']);

            // Newest first: the refund filed today, then the March events,
            // then February's payment, then January's move-in.
            expect($kinds[0])->toBe('refund');
            $dates = $rows->pluck('date')->map(fn ($date) => (string) $date)->all();
            $sorted = $dates;
            rsort($sorted);
            expect($dates)->toBe($sorted)
                ->and(end($dates))->toBe('2026-01-01');
        });
});
