<?php

use App\Enums\PlatformPermissionKey;
use App\Enums\PlatformRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');
});

function makeCustomRole(string $name = 'billing-manager', array $permissions = []): Role
{
    Role::create([
        'name' => $name,
        'guard_name' => 'web',
        'is_active' => true,
    ]);

    $role = Role::where('name', $name)->firstOrFail();
    $role->syncPermissions($permissions);

    return $role;
}

// ---------------------------------------------------------------- creating --

test('admin can create a custom role with permissions', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.roles.store'), [
            'name' => 'billing-manager',
            'permissions' => ['user.manage', 'user.edit'],
        ])
        ->assertRedirect(route('platform.roles.index'));

    $role = Role::where('name', 'billing-manager')->firstOrFail();

    expect($role->is_active)->toBeTrue()
        ->and($role->isProtected())->toBeFalse()
        ->and($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['user.edit', 'user.manage']);
});

test('a role can be created with no permissions at all', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.roles.store'), ['name' => 'auditor'])
        ->assertRedirect(route('platform.roles.index'));

    expect(Role::where('name', 'auditor')->firstOrFail()->permissions)->toHaveCount(0);
});

test('a role name must be lowercase and hyphenated', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.roles.store'), ['name' => 'Billing Manager'])
        ->assertSessionHasErrors('name');

    expect(Role::where('name', 'like', '%Billing%')->exists())->toBeFalse();
});

test('a custom role cannot reuse a built-in name', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.roles.store'), ['name' => 'admin'])
        ->assertSessionHasErrors('name');
});

test('a role cannot be created with an unknown permission', function () {
    $this->actingAs($this->admin)
        ->post(route('platform.roles.store'), [
            'name' => 'billing-manager',
            'permissions' => ['user.manage', 'not.a.permission'],
        ])
        ->assertSessionHasErrors('permissions.1');

    expect(Role::where('name', 'billing-manager')->exists())->toBeFalse();
});

test('creating a role requires the create permission', function () {
    // `manage roles` alone must not permit creating one: the split is the point.
    $this->admin->roles()->first()->permissions()->detach(
        Permission::whereIn('name', [
            PlatformPermissionKey::RoleCreate->value,
        ])->pluck('id'),
    );
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->admin)
        ->post(route('platform.roles.store'), ['name' => 'billing-manager'])
        ->assertRedirect();

    expect(Role::where('name', 'billing-manager')->exists())->toBeFalse();
});

// ------------------------------------------------------------- deactivating --

test('deactivating a custom role moves its holders to searcher', function () {
    $role = makeCustomRole();
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);

    $this->actingAs($this->admin)
        ->patch(route('platform.roles.status', $role), ['is_active' => false])
        ->assertRedirect();

    $user->refresh();

    expect($role->fresh()->is_active)->toBeFalse()
        ->and($user->hasRole('searcher'))->toBeTrue()
        ->and($user->hasRole($role))->toBeFalse();
});

test('deactivating keeps the other roles a user holds', function () {
    $role = makeCustomRole();
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role, 'occupant');

    $this->actingAs($this->admin)
        ->patch(route('platform.roles.status', $role), ['is_active' => false]);

    $user->refresh();

    // syncRoles replaces the whole set, so a naive remove-then-add would have
    // quietly dropped `occupant` too.
    expect($user->hasRole('searcher'))->toBeTrue()
        ->and($user->hasRole('occupant'))->toBeTrue();
});

test('a deactivated role can be reactivated', function () {
    $role = makeCustomRole();
    $role->forceFill(['is_active' => false])->save();

    $this->actingAs($this->admin)
        ->patch(route('platform.roles.status', $role), ['is_active' => true])
        ->assertRedirect();

    expect($role->fresh()->is_active)->toBeTrue();
});

test('a deactivated role cannot be assigned to a user', function () {
    $role = makeCustomRole();
    $role->forceFill(['is_active' => false])->save();

    $user = User::factory()->create(['status' => 'active']);

    $this->actingAs($this->admin)
        ->patch(route('platform.users.update', $user), [
            'first_name' => 'Ada',
            'email' => 'ada@example.com',
            'status' => 'active',
            'roles' => [$role->name],
        ])
        ->assertSessionHasErrors('roles.0');

    expect($user->fresh()->hasRole($role))->toBeFalse();
});

test('an active custom role can be assigned to a user', function () {
    $role = makeCustomRole();

    $user = User::factory()->create(['status' => 'active']);

    $this->actingAs($this->admin)
        ->patch(route('platform.users.update', $user), [
            'first_name' => 'Ada',
            'email' => 'ada@example.com',
            'status' => 'active',
            'roles' => [$role->name],
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->hasRole($role))->toBeTrue();
});

test('a built-in role cannot be deactivated', function () {
    $admin = Role::where('name', 'admin')->firstOrFail();

    $this->actingAs($this->admin)
        ->patch(route('platform.roles.status', $admin), ['is_active' => false])
        ->assertRedirect();

    // The refusal is a 422 toast, so assert on what did not happen.
    expect($admin->fresh()->is_active)->toBeTrue();
});

// ----------------------------------------------------------------- deleting --

test('deleting a custom role moves its holders to searcher', function () {
    $role = makeCustomRole();
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);

    $this->actingAs($this->admin)
        ->delete(route('platform.roles.destroy', $role), ['password' => 'password'])
        ->assertRedirect(route('platform.roles.index'));

    $user->refresh();

    expect(Role::where('name', 'billing-manager')->exists())->toBeFalse()
        ->and($user->hasRole('searcher'))->toBeTrue();
});

test('deleting a role requires the current password', function () {
    $role = makeCustomRole();

    $this->actingAs($this->admin)
        ->delete(route('platform.roles.destroy', $role), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    expect(Role::where('name', 'billing-manager')->exists())->toBeTrue();
});

test('deleting a role leaves no dangling pivot rows', function () {
    $role = makeCustomRole();
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);
    $role->givePermissionTo('user.manage');

    $this->actingAs($this->admin)
        ->delete(route('platform.roles.destroy', $role), ['password' => 'password']);

    expect(DB::table('model_has_roles')->where('role_id', $role->id)->count())->toBe(0)
        ->and(DB::table('role_has_permissions')->where('role_id', $role->id)->count())->toBe(0);
});

test('a built-in role cannot be deleted', function (string $name) {
    $role = Role::where('name', $name)->firstOrFail();

    $this->actingAs($this->admin)
        ->delete(route('platform.roles.destroy', $role), ['password' => 'password'])
        ->assertRedirect();

    expect(Role::where('name', $name)->exists())->toBeTrue();
})->with(['admin', 'organization-owner', 'searcher', 'occupant']);

test('deleting a role requires the delete permission', function () {
    $role = makeCustomRole();

    $this->admin->roles()->first()->permissions()->detach(
        Permission::whereIn('name', [
            PlatformPermissionKey::RoleDelete->value,
        ])->pluck('id'),
    );
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->admin)
        ->delete(route('platform.roles.destroy', $role), ['password' => 'password'])
        ->assertRedirect();

    expect(Role::where('name', 'billing-manager')->exists())->toBeTrue();
});

// ------------------------------------------------------------ import/export --

test('admin can import custom roles from a csv', function () {
    $csv = "Name,Permissions,Status\n"
        ."billing-manager,user.manage|user.edit,active\n"
        ."auditor,view audit,inactive\n";

    $this->actingAs($this->admin)
        ->post(route('platform.roles.import'), [
            'file' => UploadedFile::fake()->createWithContent('roles.csv', $csv),
        ])
        ->assertRedirect(route('platform.roles.index'));

    expect(Role::where('name', 'billing-manager')->exists())->toBeTrue();
    expect(Role::where('name', 'auditor')->firstOrFail()->is_active)->toBeFalse();
});

test('role import reports per-row errors and keeps the good rows', function () {
    $csv = "Name,Permissions\n"
        ."billing-manager,user.manage\n"
        ."admin,user.manage\n"
        ."Billing Manager,user.manage\n"
        ."auditor,not.a.permission\n";

    $this->actingAs($this->admin)
        ->post(route('platform.roles.import'), [
            'file' => UploadedFile::fake()->createWithContent('roles.csv', $csv),
        ])
        ->assertRedirect();

    $errors = collect(session('inertia.flash_data')['importErrors'] ?? [])->all();

    expect($errors)->toHaveCount(3)
        ->and(collect($errors)->pluck('row')->all())->toBe([3, 4, 5]);

    expect(Role::where('name', 'billing-manager')->exists())->toBeTrue();
    expect(Role::where('name', 'auditor')->exists())->toBeFalse();
});

test('role export honours the table filters', function () {
    makeCustomRole('billing-manager');
    makeCustomRole('auditor');

    $body = $this->actingAs($this->admin)
        ->get(route('platform.roles.export', ['type' => 'custom', 'search' => 'billing']))
        ->streamedContent();

    expect($body)->toContain('billing-manager')
        ->and($body)->not->toContain('auditor');
});

test('role export can be limited to the current page', function () {
    makeCustomRole('a-extra');

    $body = $this->actingAs($this->admin)
        ->get(route('platform.roles.export', ['scope' => 'page', 'page' => 1]))
        ->streamedContent();

    // Four built-ins plus one custom, plus the header line.
    expect(substr_count(trim($body), "\n"))->toBe(5);
});

// -------------------------------------------------------------- index shape --

test('the index paginates and reports the new capability flags', function () {
    makeCustomRole();

    $this->actingAs($this->admin)
        ->get(route('platform.roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/roles/index')
            ->has('roles.data')
            ->has('roles.total')
            ->where('canCreate', true)
            ->where('canDelete', true)
            ->where('canImport', true)
            ->where('canExport', true),
        );
});

test('the index can filter to custom roles only', function () {
    makeCustomRole('billing-manager');

    $this->actingAs($this->admin)
        ->get(route('platform.roles.index', ['type' => 'custom']))
        ->assertInertia(fn ($page) => $page
            ->where('roles.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['billing-manager']),
        );
});

test('a deactivated role is marked inactive on the index', function () {
    $role = makeCustomRole();
    $role->forceFill(['is_active' => false])->save();

    $this->actingAs($this->admin)
        ->get(route('platform.roles.index', ['search' => 'billing']))
        ->assertInertia(fn ($page) => $page
            ->where('roles.data.0.is_active', false)
            ->where('roles.data.0.is_protected', false),
        );
});

test('the four built-in roles are all reported as protected', function () {
    $this->actingAs($this->admin)
        ->get(route('platform.roles.index'))
        ->assertInertia(fn ($page) => $page
            ->where('roles.data', function ($rows) {
                $protected = collect($rows)->where('is_protected', true)->pluck('name');

                return $protected->sort()->values()->all()
                    === collect(PlatformRole::cases())->map->value->sort()->values()->all();
            }),
        );
});
