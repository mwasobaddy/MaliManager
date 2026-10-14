<?php

namespace Database\Seeders;

use App\Enums\PlatformPermissionKey;
use App\Enums\PlatformRole;
use App\Enums\SubPermissionKey;
use App\Models\Organization;
use App\Models\SubPermission;
use App\Support\DefaultSubRoles;
use App\Support\PropertyTypeCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the platform roles and the fixed sub-permission catalog.
     */
    public function run(): void
    {
        // Ensure a stale permission cache doesn't cause duplicate inserts.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PlatformRole::cases() as $role) {
            Role::findOrCreate($role->value);
        }

        // Central (platform-wide) permissions, assignable to roles from the
        // settings/roles UI. Grant all of them to the admin role by default.
        $permissionIds = [];
        foreach (PlatformPermissionKey::cases() as $key) {
            $permission = Permission::findOrCreate($key->value, 'web');
            $permissionIds[$key->value] = $permission->id;
        }

        $adminRole = Role::where('name', PlatformRole::Admin->value)->first();
        if ($adminRole) {
            $adminRole->permissions()->sync($permissionIds, detaching: false);
        }

        // Each non-admin platform role gets the metrics permission that
        // unlocks its dashboard tab. Owners and staff share the org tab;
        // searchers and occupants get their own.
        $this->grant($permissionIds, PlatformRole::OrganizationOwner, PlatformPermissionKey::ViewOrgMetrics);
        $this->grant($permissionIds, PlatformRole::Searcher, PlatformPermissionKey::ViewSearcherMetrics);
        $this->grant($permissionIds, PlatformRole::Occupant, PlatformPermissionKey::ViewOccupantMetrics);

        // The AI assistant is available to anyone with a configured credential
        // by default; platform admins can revoke it per role.
        $this->grant($permissionIds, PlatformRole::Searcher, PlatformPermissionKey::AiUse);
        $this->grant($permissionIds, PlatformRole::Occupant, PlatformPermissionKey::AiUse);

        $sortOrder = 0;
        foreach (SubPermissionKey::cases() as $key) {
            SubPermission::withTrashed()->updateOrCreate(
                ['key' => $key->value],
                [
                    'module' => $key->module(),
                    'name' => $key->label(),
                    'sort_order' => $sortOrder++,
                ]
            );
        }

        // Retire catalog entries whose keys were removed from the enum
        // (e.g. the user.* keys promoted to central permissions).
        SubPermission::whereNotIn('key', array_column(SubPermissionKey::cases(), 'value'))->delete();

        // Hand organizations created before a bundle gained a permission the
        // missing keys. Additive only — owner customisations are preserved.
        foreach (Organization::all() as $organization) {
            DefaultSubRoles::syncBundlesFor($organization);
        }

        // The property-type catalog is central configuration with no
        // organization dimension, so it is seeded once rather than per tenant.
        // Additive for the same reason as above: an admin who renamed or
        // deleted a type keeps their version.
        PropertyTypeCatalog::seed();
    }

    /**
     * Give a single platform permission to a platform role without
     * disturbing any other permissions it may hold.
     */
    private function grant(array $permissionIds, PlatformRole $role, PlatformPermissionKey $key): void
    {
        $roleModel = Role::where('name', $role->value)->first();

        if ($roleModel) {
            $roleModel->permissions()->syncWithoutDetaching([$permissionIds[$key->value]]);
        }
    }
}
