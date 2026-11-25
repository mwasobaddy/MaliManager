<?php

use App\Models\Lease;
use App\Models\MoveOutNotice;
use App\Models\Occupant;
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

function noticeGraph(): array
{
    $owner = User::factory()->create(['onboarded_at' => now()]);

    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Notice Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $property = $organization->properties()->create([
        'name' => 'Notice Court',
        'slug' => 'notice-court',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $unit = Unit::factory()->create(['property_id' => $property->id]);

    $person = Person::factory()->create(['first_name' => 'Moving', 'last_name' => 'Tenant']);
    $tenant = User::factory()->create(['onboarded_at' => now(), 'person_id' => $person->id]);

    $lease = Lease::create([
        'person_id' => $person->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'deposit' => 50000,
        'rent_amount' => 25000,
        'rent_frequency' => 'monthly',
        'currency' => 'KES',
        'status' => 'active',
        'starts_at' => now()->subYear()->toDateString(),
        'created_by' => $owner->id,
    ]);

    Occupant::factory()->create([
        'organization_id' => $organization->id,
        'person_id' => $person->id,
        'status' => 'active',
    ]);

    return [$owner, $tenant, $organization, $lease];
}

function noticeUrl(string $path = ''): string
{
    return "http://notice-estates.test{$path}";
}

function noticeTenantUrl(Organization $organization, string $path = ''): string
{
    $domain = $organization->tenant->domains()->value('domain');

    return "http://{$domain}{$path}";
}

function noticeFutureDate(int $days = 45): string
{
    return now()->addDays($days)->toDateString();
}

test('a tenant can give 30 days notice on their active lease', function () {
    [$owner, $tenant, $organization, $lease] = noticeGraph();

    $this->actingAs($tenant)
        ->post(noticeUrl('/searcher/move-out-notices'), [
            'lease_id' => $lease->id,
            'move_out_date' => noticeFutureDate(45),
            'note' => 'Relocating for work.',
        ])
        ->assertRedirect();

    $notice = MoveOutNotice::firstOrFail();

    expect($notice->lease_id)->toBe($lease->id)
        ->and($notice->status->value)->toBe('pending')
        ->and($notice->move_out_date->toDateString())->toBe(noticeFutureDate(45))
        ->and($notice->requested_by)->toBe($tenant->id);
});

test('a notice under 30 days out is rejected', function () {
    [$owner, $tenant, $organization, $lease] = noticeGraph();

    $this->actingAs($tenant)
        ->from(noticeUrl('/searcher/rentals'))
        ->post(noticeUrl('/searcher/move-out-notices'), [
            'lease_id' => $lease->id,
            'move_out_date' => now()->addDays(10)->toDateString(),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('move_out_date');

    expect(MoveOutNotice::count())->toBe(0);
});

test('a second pending notice on the same lease is rejected', function () {
    [$owner, $tenant, $organization, $lease] = noticeGraph();

    MoveOutNotice::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'requested_by' => $tenant->id,
        'move_out_date' => noticeFutureDate(45),
        'status' => 'pending',
    ]);

    $this->actingAs($tenant)
        ->from(noticeUrl('/searcher/rentals'))
        ->post(noticeUrl('/searcher/move-out-notices'), [
            'lease_id' => $lease->id,
            'move_out_date' => noticeFutureDate(60),
        ])
        ->assertRedirect();
    assertErrorToast();

    expect(MoveOutNotice::count())->toBe(1);
});

test('a tenant cannot give notice on someone else’s lease', function () {
    [$owner, $tenant, $organization, $lease] = noticeGraph();

    $stranger = User::factory()->create(['onboarded_at' => now(), 'person_id' => Person::factory()->create()->id]);

    $this->actingAs($stranger)
        ->from(noticeUrl('/searcher/rentals'))
        ->post(noticeUrl('/searcher/move-out-notices'), [
            'lease_id' => $lease->id,
            'move_out_date' => noticeFutureDate(45),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('lease_id');

    expect(MoveOutNotice::count())->toBe(0);
});

test('a tenant can withdraw their pending notice', function () {
    [$owner, $tenant, $organization, $lease] = noticeGraph();

    $notice = MoveOutNotice::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'requested_by' => $tenant->id,
        'move_out_date' => noticeFutureDate(45),
        'status' => 'pending',
    ]);

    $this->actingAs($tenant)
        ->delete(noticeUrl("/searcher/move-out-notices/{$notice->id}"))
        ->assertRedirect();

    expect($notice->fresh()->status->value)->toBe('withdrawn');
});

test('a landlord acknowledges a pending notice', function () {
    [$owner, $tenant, $organization, $lease] = noticeGraph();

    $notice = MoveOutNotice::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'requested_by' => $tenant->id,
        'move_out_date' => noticeFutureDate(45),
        'status' => 'pending',
    ]);

    $this->actingAs($owner)
        ->post(noticeTenantUrl($organization, "/move-out-notices/{$notice->id}/acknowledge"))
        ->assertRedirect();

    expect($notice->fresh()->status->value)->toBe('acknowledged');
});

test('an acknowledged notice cannot be acknowledged again', function () {
    [$owner, $tenant, $organization, $lease] = noticeGraph();

    $notice = MoveOutNotice::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'requested_by' => $tenant->id,
        'move_out_date' => noticeFutureDate(45),
        'status' => 'acknowledged',
    ]);

    $this->actingAs($owner)
        ->from(noticeUrl('/deposit-refunds'))
        ->post(noticeTenantUrl($organization, "/move-out-notices/{$notice->id}/acknowledge"))
        ->assertRedirect()
        ->assertSessionHasErrors('notice');
});

test('the dashboard flags ended leases holding deposits and pending notices', function () {
    [$owner, $tenant, $organization, $lease] = noticeGraph();

    $lease->update(['status' => 'ended', 'ends_at' => now()->toDateString()]);

    $unitB = Unit::factory()->create(['property_id' => $lease->property_id]);
    $active = Lease::create([
        'person_id' => $lease->person_id,
        'organization_id' => $organization->id,
        'property_id' => $lease->property_id,
        'unit_id' => $unitB->id,
        'deposit' => 10000,
        'rent_amount' => 25000,
        'rent_frequency' => 'monthly',
        'currency' => 'KES',
        'status' => 'active',
        'starts_at' => now()->subMonths(2)->toDateString(),
        'created_by' => $owner->id,
    ]);

    MoveOutNotice::create([
        'organization_id' => $organization->id,
        'lease_id' => $active->id,
        'requested_by' => $tenant->id,
        'move_out_date' => noticeFutureDate(45),
        'status' => 'pending',
    ]);

    $this->actingAs($owner)
        ->get(noticeTenantUrl($organization, '/overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('flags', function ($flags) {
                $messages = collect($flags)->pluck('message')->join(' | ');

                return str_contains($messages, 'deposit')
                    && str_contains($messages, 'move-out');
            })
        );
});

test('the lease sheet carries refund state and the pending notice', function () {
    [$owner, $tenant, $organization, $lease] = noticeGraph();

    $lease->update(['status' => 'ended', 'ends_at' => now()->toDateString()]);

    MoveOutNotice::create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'requested_by' => $tenant->id,
        'move_out_date' => noticeFutureDate(45),
        'status' => 'pending',
    ]);

    $this->actingAs($owner)
        ->get(noticeTenantUrl($organization, "/notice-court/leases?mode=view&lease={$lease->id}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('focusedLease.refund_state', 'missing')
            ->where('focusedLease.move_out_notice.move_out_date', noticeFutureDate(45))
        );
});
