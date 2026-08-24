<?php

namespace Database\Seeders;

use App\Enums\PlatformPermissionKey;
use App\Enums\PlatformRole;
use App\Enums\SubPermissionKey;
use App\Models\SubPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the platform roles and the fixed sub-permission catalog.
     */
    public function run(): void
    {
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
    }
}
