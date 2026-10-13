<?php

use App\Enums\PlatformPermissionKey;
use App\Enums\PlatformRole;
use App\Models\User;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;
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
        ->get(route('platform.roles.index'))
        ->assertRedirect();
});

test('admin can view the roles settings page', function () {
    $admin = User::factory()->create();
    $admin->assignRole(PlatformRole::Admin->value);

    $this->actingAs($admin)
        ->get(route('platform.roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/roles/index')
            ->where('roles', fn ($roles) => count($roles) === count(PlatformRole::cases()))
            ->where('permissionCatalog', fn ($catalog) => count($catalog) === count(PlatformPermissionKey::cases()))
            // Role assignment moved to the users index, so this page no longer
            // loads or ships a user list.
            ->missing('users'));
});

test('updating role permissions without a password confirmation redirects to confirm', function () {
    $admin = User::factory()->create();
    $admin->assignRole(PlatformRole::Admin->value);

    $role = Role::where('name', PlatformRole::Admin->value)->first();

    $this->actingAs($admin)
        ->patch(route('platform.roles.update', $role), [
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
        ->patch(route('platform.roles.update', $role), [
            'permissions' => [PlatformPermissionKey::ExportAudit->value],
        ])
        ->assertRedirect();

    expect($role->fresh()->hasPermissionTo(PlatformPermissionKey::ExportAudit->value))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo(PlatformPermissionKey::ViewAudit->value))->toBeFalse();
});

test('platform roles are assigned from the users index, not this page', function () {
    // The roles page used to render a user table whose per-row picker called
    // `PATCH /platform/users/{user}/role` and `syncRoles()`. That replaced the
    // whole set, so a user with two roles silently lost one while a success
    // toast said otherwise — and the four role names were hardcoded in
    // TypeScript, duplicating the PlatformRole enum. The users index does it
    // properly, so the endpoint is gone rather than merely unreferenced.
    expect(Route::has('platform.users.role'))->toBeFalse();

    $admin = User::factory()->create();
    $admin->assignRole(PlatformRole::Admin->value);
    $user = User::factory()->create(['onboarded_at' => now()]);
    $user->assignRole(PlatformRole::Searcher->value);

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('platform.users.update', $user), [
            'first_name' => 'Renamed',
            'last_name' => 'User',
            'email' => $user->email,
            'status' => 'active',
            'roles' => [PlatformRole::OrganizationOwner->value],
        ])
        ->assertRedirect();

    expect($user->fresh()->roles->pluck('name')->toArray())->toBe([PlatformRole::OrganizationOwner->value]);
});

test('a user granted the view audit permission can access the admin audit log', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PlatformPermissionKey::ViewAudit->value);

    $this->actingAs($user)
        ->get(route('platform.audit.index'))
        ->assertOk();
});
