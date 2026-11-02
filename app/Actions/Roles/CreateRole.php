<?php

namespace App\Actions\Roles;

use App\Actions\Roles\Concerns\RoleHelpers;
use App\Models\Role;
use App\Services\Service;

class CreateRole extends Service
{
    use RoleHelpers;

    public function handle(string $name, array $permissionNames, bool $isActive = true): Role
    {
        $role = Role::create([
            'name' => $name,
            'guard_name' => 'web',
            'is_active' => $isActive,
        ]);

        $role->syncPermissions($this->resolvePermissions($permissionNames));

        $this->forgetPermissionCache();

        return $role;
    }
}
