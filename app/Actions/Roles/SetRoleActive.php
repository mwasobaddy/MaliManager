<?php

namespace App\Actions\Roles;

use App\Actions\Roles\Concerns\RoleHelpers;
use App\Models\Role;
use App\Services\Service;

class SetRoleActive extends Service
{
    use RoleHelpers;

    public function handle(Role $role, bool $isActive): void
    {
        if ($role->isProtected()) {
            abort(422, 'Built-in roles cannot be deactivated.');
        }

        if (! $isActive) {
            $this->reassignHolders($role);
        }

        $role->forceFill(['is_active' => $isActive])->save();

        $this->forgetPermissionCache();
    }
}
