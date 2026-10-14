<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Enums\PlatformPermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateRolePermissionsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesController extends Controller
{
    /**
     * The platform roles and the central permissions each one carries.
     *
     * Role assignment does *not* live here. It used to — this page also
     * rendered a table of users with a per-row role picker — but the users
     * index already does it properly, through `PATCH /users/{user}`, with the
     * role list coming from the `PlatformRole` enum. The copy here hardcoded
     * the same four names in TypeScript and called `syncRoles()`, which
     * *replaces* the set: a user with two roles silently lost one while a
     * success toast said otherwise. One place to change a role, and it is the
     * one that validates against the enum.
     *
     * Each row carries its own assigned-permission keys so the edit sheet
     * renders from the URL alone, with no second round trip to resolve it.
     */
    public function index(): Response
    {
        $roles = Role::with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions
                    ->map(fn (Permission $permission): array => [
                        'value' => $permission->name,
                        'label' => $this->permissionLabel($permission->name),
                    ])
                    ->values()
                    ->all(),
            ]);

        return Inertia::render('platform/roles/index', [
            'roles' => $roles,
            'permissionCatalog' => collect(PlatformPermissionKey::cases())
                ->map(fn (PlatformPermissionKey $key): array => [
                    'value' => $key->value,
                    'label' => $key->label(),
                ])
                ->values()
                ->all(),
            'canEdit' => request()->user()?->hasPermissionTo(PlatformPermissionKey::ManageRoles->value) ?? false,
        ]);
    }

    /**
     * The human label for a permission key, falling back to the raw key for
     * anything a role holds that the enum no longer declares.
     */
    private function permissionLabel(string $value): string
    {
        foreach (PlatformPermissionKey::cases() as $key) {
            if ($key->value === $value) {
                return $key->label();
            }
        }

        return $value;
    }

    /**
     * Sync the central permissions for a role (password-protected).
     */
    public function update(UpdateRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        $permissions = Permission::whereIn('name', $request->validated('permissions', []))->get()->all();

        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Permissions for {$role->name} updated."]);

        return to_route('platform.roles.index');
    }
}
