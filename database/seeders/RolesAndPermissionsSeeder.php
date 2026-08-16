<?php

namespace Database\Seeders;

use App\Enums\PlatformRole;
use App\Enums\SubPermissionKey;
use App\Models\SubPermission;
use Illuminate\Database\Seeder;
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
