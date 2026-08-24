<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\PlatformPermissionKey;
use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesController extends Controller
{
    /**
     * Display all platform roles with their central permissions and
     * a few assignable users.
     */
    public function index(Request $request): Response
    {
        $roles = Role::with('permissions')
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions
                        ->map(fn ($permission) => $permission->name)
                        ->all(),
                ];
            });

        $permissionCatalog = collect(PlatformPermissionKey::cases())
            ->map(fn (PlatformPermissionKey $key) => ['value' => $key->value, 'label' => $key->label()])
            ->values()
            ->all();

        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
            ]);

        return Inertia::render('settings/roles', [
            'roles' => $roles,
            'permissions' => $permissionCatalog,
            'users' => $users,
            'isAdmin' => $request->user()?->hasPermissionTo(PlatformPermissionKey::ManageRoles->value),
        ]);
    }

    /**
     * Show the central permissions assigned to a role.
     */
    public function edit(Request $request, Role $role): Response
    {
        $permissionCatalog = collect(PlatformPermissionKey::cases())
            ->map(fn (PlatformPermissionKey $key) => [
                'value' => $key->value,
                'label' => $key->label(),
                'assigned' => $role->hasPermissionTo($key->value),
            ])
            ->values()
            ->all();

        return Inertia::render('settings/roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
            ],
            'permissions' => $permissionCatalog,
            'isAdmin' => true,
        ]);
    }

    /**
     * Sync the central permissions for a role (password-protected).
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(collect(PlatformPermissionKey::cases())->map->value->all())],
        ]);

        $permissions = Permission::whereIn('name', $validated['permissions'] ?? [])->get()->all();

        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Permissions for {$role->name} updated."]);

        return to_route('settings.roles.edit', $role);
    }

    /**
     * Replace a user's platform role (password-protected).
     */
    public function assignRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(collect(PlatformRole::cases())->map->value->all())],
        ]);

        $user->syncRoles([$validated['role']]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$user->name} is now {$validated['role']}.",
        ]);

        return back();
    }
}
