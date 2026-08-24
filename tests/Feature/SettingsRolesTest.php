<?php

use App\Enums\PlatformPermissionKey;
use App\Enums\PlatformRole;
use App\Models\User;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

test('central permissions are seeded and granted to the platform admin role', function () {
    $admin = Role::where('name', PlatformRole::Admin->value)->first();

    expect($admin)->not->toBeNull()
        ->and($admin->permissions->count())->toBe(count(PlatformPermissionKey::cases()))
        ->and(Permission::whereIn('name', collect(PlatformPermissionKey::cases())->map->value)->count())
        ->toBe(count(PlatformPermissionKey::cases()));
});

test('non-admin users are redirected from the roles settings', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);

    $this->actingAs($user)
        ->get(route('settings.roles.index'))
        ->assertRedirect();
});

test('admin can view the roles settings page', function () {
    $admin = User::factory()->create();
    $admin->assignRole(PlatformRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('settings.roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/roles')
            ->where('roles', fn ($roles) => count($roles) === count(PlatformRole::cases()))
            ->where('permissions', fn ($permissions) => count($permissions) === count(PlatformPermissionKey::cases())));
});

test('updating role permissions without a password confirmation redirects to confirm', function () {
    $admin = User::factory()->create();
    $admin->assignRole(PlatformRole::Admin->value);

    $role = Role::where('name', PlatformRole::Admin->value)->first();

    $this->actingAs($admin)
        ->patch(route('settings.roles.update', $role), [
            'permissions' => [PlatformPermissionKey::ViewAudit->value],
        ])
        ->assertRedirect(route('password.confirm'));
});

test('admin can update a role permissions after confirming password', function () {
    $admin = User::factory()->create();
    $admin->assignRole(PlatformRole::Admin->value);

    $role = Role::where('name', PlatformRole::Admin->value)->first();

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('settings.roles.update', $role), [
            'permissions' => [PlatformPermissionKey::ExportAudit->value],
        ])
        ->assertRedirect();

    expect($role->fresh()->hasPermissionTo(PlatformPermissionKey::ExportAudit->value))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo(PlatformPermissionKey::ViewAudit->value))->toBeFalse();
});

test('admin can assign a user to a different platform role after confirming password', function () {
    $admin = User::factory()->create();
    $admin->assignRole(PlatformRole::Admin->value);
    $tenant = User::factory()->create(['onboarded_at' => now()]);
    $tenant->assignRole(PlatformRole::Tenant->value);

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('settings.users.role', $tenant), [
            'role' => PlatformRole::OrganizationOwner->value,
        ])
        ->assertRedirect();

    expect($tenant->fresh()->roles->pluck('name')->toArray())->toContain(PlatformRole::OrganizationOwner->value);
});

test('a user granted the view audit permission can access the admin audit log', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PlatformPermissionKey::ViewAudit->value);

    $this->actingAs($user)
        ->get(route('admin.audit.index'))
        ->assertOk();
});
