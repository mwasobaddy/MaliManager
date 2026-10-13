<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');
});

test('platform admin can view plans index', function () {
    Plan::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->get(route('platform.plans.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/plans/index')
            ->has('plans.data', 2),
        );
});

test('platform admin can create a plan', function () {
    $response = $this->actingAs($this->admin)->post(route('platform.plans.store'), [
        'name' => 'Pro',
        'slug' => 'pro',
        'currency' => 'KES',
        'price' => 5000,
        'properties_limit' => 10,
        'units_limit' => 100,
        'is_active' => true,
        'sort_order' => 1,
        'features' => ['Priority support', 'SMS alerts'],
    ]);

    $response->assertRedirect(route('platform.plans.index'));

    $plan = Plan::where('slug', 'pro')->firstOrFail();

    expect($plan->features)->toBe(['Priority support', 'SMS alerts'])
        ->and($plan->properties_limit)->toBe(10);

    assertSuccessToast();
});

test('a plan is created with every capability off when the boxes are unticked', function () {
    // The shadcn Checkbox renders a <button>, so an unticked box must still
    // submit an explicit "0" — otherwise `nullable|boolean` leaves the
    // previous value in place on update.
    $this->actingAs($this->admin)->post(route('platform.plans.store'), [
        'name' => 'Bare',
        'slug' => 'bare',
        'currency' => 'KES',
        'price' => 0,
        'is_active' => '0',
        'has_dedicated_db' => '0',
        'has_custom_domain' => '0',
        'has_email_notifications' => '0',
        'has_sms_notifications' => '0',
    ])->assertRedirect(route('platform.plans.index'));

    $plan = Plan::where('slug', 'bare')->firstOrFail();

    expect($plan->is_active)->toBeFalse()
        ->and($plan->has_dedicated_db)->toBeFalse()
        ->and($plan->has_custom_domain)->toBeFalse()
        ->and($plan->has_email_notifications)->toBeFalse()
        ->and($plan->has_sms_notifications)->toBeFalse();
});

test('platform admin can update a plan', function () {
    $plan = Plan::factory()->create(['name' => 'Old', 'slug' => 'old']);

    $this->actingAs($this->admin)->patch(route('platform.plans.update', $plan), [
        'name' => 'New',
        'slug' => 'new',
        'currency' => 'USD',
        'price' => 9000,
        'is_active' => '1',
        'sort_order' => 2,
    ])->assertRedirect(route('platform.plans.index'));

    expect($plan->fresh()->name)->toBe('New');
});

test('the legacy create and edit pages redirect to the overlay urls', function () {
    $plan = Plan::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('platform.plans.create'))
        ->assertRedirect(route('platform.plans.index', ['create' => 1]));

    $this->actingAs($this->admin)
        ->get(route('platform.plans.edit', $plan))
        ->assertRedirect(route('platform.plans.index', ['mode' => 'edit', 'plan' => $plan->id]));
});

test('deleting a plan requires the current password', function () {
    $plan = Plan::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('platform.plans.destroy', $plan))
        ->assertSessionHasErrors('password');

    expect(Plan::withTrashed()->find($plan->id))->not->toBeNull();

    $this->actingAs($this->admin)
        ->delete(route('platform.plans.destroy', $plan), [
            'password' => 'password',
        ])
        ->assertRedirect(route('platform.plans.index'));

    expect(Plan::find($plan->id))->toBeNull();
    expect(Plan::withTrashed()->find($plan->id))->not->toBeNull();
    assertSuccessToast();
});

test('the index filters by search across name, slug and description', function () {
    Plan::factory()->create(['name' => 'Gold', 'slug' => 'gold', 'description' => 'Premium tier']);
    Plan::factory()->create(['name' => 'Silver', 'slug' => 'silver', 'description' => 'Cheap tier']);

    $this->actingAs($this->admin)
        ->get(route('platform.plans.index', ['search' => 'silver']))
        ->assertInertia(fn ($page) => $page
            ->component('platform/plans/index')
            ->has('plans.data', 1)
            ->where('filters.search', 'silver'),
        );

    $this->actingAs($this->admin)
        ->get(route('platform.plans.index', ['search' => 'Premium']))
        ->assertInertia(fn ($page) => $page->has('plans.data', 1));
});

test('the index filters by status', function () {
    Plan::factory()->create(['name' => 'Live', 'is_active' => true]);
    Plan::factory()->create(['name' => 'Retired', 'is_active' => false]);

    $this->actingAs($this->admin)
        ->get(route('platform.plans.index', ['status' => 'active']))
        ->assertInertia(fn ($page) => $page
            ->has('plans.data', 1)
            ->where('filters.status', 'active'),
        );

    $this->actingAs($this->admin)
        ->get(route('platform.plans.index', ['status' => 'inactive']))
        ->assertInertia(fn ($page) => $page->has('plans.data', 1));
});

test('the index sorts server-side and ignores a crafted sort column', function () {
    Plan::factory()->create(['name' => 'Beta', 'price' => 100]);
    Plan::factory()->create(['name' => 'Alpha', 'price' => 900]);

    $this->actingAs($this->admin)
        ->get(route('platform.plans.index', ['sort' => 'price', 'direction' => 'asc']))
        ->assertInertia(fn ($page) => $page
            ->where('sort.column', 'price')
            ->where('sort.direction', 'asc')
            ->where('plans.data.0.name', 'Beta')
            ->where('plans.data.1.name', 'Alpha'),
        );

    // Not in the allowlist, so it must not reach the ORDER BY.
    $this->actingAs($this->admin)
        ->get(route('platform.plans.index', ['sort' => 'slug; drop table plans']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('sort.column', null));
});

test('the default order is the merchandising order, not newest first', function () {
    Plan::factory()->create(['name' => 'Late', 'sort_order' => 9]);
    Plan::factory()->create(['name' => 'Early', 'sort_order' => 1]);

    $this->actingAs($this->admin)
        ->get(route('platform.plans.index'))
        ->assertInertia(fn ($page) => $page
            ->where('plans.data.0.name', 'Early')
            ->where('plans.data.1.name', 'Late'),
        );
});

test('the index counts the organizations on each plan', function () {
    $plan = Plan::factory()->create(['sort_order' => 0]);
    Organization::factory()->count(2)->create(['plan_id' => $plan->id]);
    Plan::factory()->create(['sort_order' => 1]);

    $this->actingAs($this->admin)
        ->get(route('platform.plans.index'))
        ->assertInertia(fn ($page) => $page
            ->where('plans.data.0.organizations_count', 2)
            ->where('plans.data.1.organizations_count', 0),
        );
});

test('an off-page plan id in the url resolves the row for the overlay', function () {
    // Pin the fillers to sort_order 0 so the target genuinely lands on page 2
    // regardless of what the factory gives the rest of the fields.
    $onPage = collect(range(1, 15))->map(fn (int $i) => Plan::factory()->create([
        'name' => "Filler {$i}",
        'sort_order' => 0,
    ]));
    $offPage = Plan::factory()->create(['name' => 'Off page', 'sort_order' => 99]);

    $this->actingAs($this->admin)
        ->get(route('platform.plans.index', ['plan' => $offPage->id]))
        ->assertInertia(fn ($page) => $page
            ->component('platform/plans/index')
            ->where('focusedPlan.id', $offPage->id),
        );

    // A row that is already on the page is not sent twice.
    $this->actingAs($this->admin)
        ->get(route('platform.plans.index', ['plan' => $onPage->first()->id]))
        ->assertInertia(fn ($page) => $page->where('focusedPlan', null));
});

test('a plan can be deactivated from the table', function () {
    $plan = Plan::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)
        ->patch(route('platform.plans.status', $plan), ['is_active' => false])
        ->assertRedirect();

    expect($plan->fresh()->is_active)->toBeFalse();
});

test('deactivating a plan is rejected without the flag', function () {
    $plan = Plan::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)
        ->patch(route('platform.plans.status', $plan))
        ->assertSessionHasErrors('is_active');

    expect($plan->fresh()->is_active)->toBeTrue();
});

test('user without plan permission is forbidden', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('platform.plans.index'))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->post(route('platform.plans.store'), [
            'name' => 'X',
            'slug' => 'x',
            'currency' => 'KES',
            'price' => 0,
        ])
        ->assertRedirect(route('dashboard'));
});
