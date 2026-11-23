<?php

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');
});

test('platform trash index renders with module counts', function () {
    $plan = Plan::factory()->create(['slug' => 'trash-me']);
    $plan->delete();

    $this->actingAs($this->admin)
        ->get(route('platform.trash.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/trash/index')
            ->where('activeModule', 'plans')
            ->has('items.data', 1),
        );
});

test('a platform admin can restore a trashed plan', function () {
    $plan = Plan::factory()->create(['slug' => 'restore-me']);
    $plan->delete();

    $this->actingAs($this->admin)
        ->post(route('platform.trash.restore'), [
            'module' => 'plans',
            'ids' => [$plan->id],
            'password' => 'password',
        ])
        ->assertRedirect();

    expect($plan->fresh()->trashed())->toBeFalse();
});

test('a platform admin can permanently delete from trash', function () {
    $plan = Plan::factory()->create(['slug' => 'gone-forever']);
    $plan->delete();

    $this->actingAs($this->admin)
        ->delete(route('platform.trash.force'), [
            'module' => 'plans',
            'ids' => [$plan->id],
            'password' => 'password',
        ])
        ->assertRedirect();

    expect(Plan::withTrashed()->find($plan->id))->toBeNull();
});

test('platform trash index lists trashed roles', function () {
    $role = App\Models\Role::create([
        'name' => 'transient-auditor',
        'guard_name' => 'web',
        'is_active' => true,
    ]);
    $role->delete();

    $this->actingAs($this->admin)
        ->get(route('platform.trash.index', ['module' => 'roles']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/trash/index')
            ->where('activeModule', 'roles')
            ->has('items.data', 1),
        );
});

test('unknown modules are refused, not resolved', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.trash.restore'), [
            'module' => 'leases',
            'ids' => [1],
            'password' => 'password',
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('plans', Plan::count());
});
