<?php

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

test('platform admin can view plans index', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    Plan::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('plans.index'))
        ->assertOk();
});

test('platform admin can create a plan', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('plans.store'), [
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

    $response->assertRedirect(route('plans.index'));
    expect(Plan::where('slug', 'pro')->exists())->toBeTrue();
});

test('platform admin can edit and update a plan', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $plan = Plan::factory()->create(['name' => 'Old', 'slug' => 'old']);

    $this->actingAs($admin)
        ->get(route('plans.edit', $plan))
        ->assertOk();

    $response = $this->actingAs($admin)->patch(route('plans.update', $plan), [
        'name' => 'New',
        'slug' => 'new',
        'currency' => 'USD',
        'price' => 9000,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $response->assertRedirect(route('plans.index'));
    expect($plan->fresh()->name)->toBe('New');
});

test('platform admin can delete a plan', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $plan = Plan::factory()->create();

    $response = $this->actingAs($admin)
        ->delete(route('plans.destroy', $plan));

    $response->assertRedirect(route('plans.index'));
    expect(Plan::find($plan->id))->toBeNull();
    expect(Plan::withTrashed()->find($plan->id))->not->toBeNull();
});

test('user without plan permission is forbidden', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('plans.index'))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->post(route('plans.store'), [
            'name' => 'X',
            'slug' => 'x',
            'currency' => 'KES',
            'price' => 0,
        ])
        ->assertRedirect(route('dashboard'));
});
