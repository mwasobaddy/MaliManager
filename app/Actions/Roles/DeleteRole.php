<?php

namespace App\Actions\Roles;

use App\Actions\Roles\Concerns\RoleHelpers;
use App\Models\Role;
use App\Services\Service;
use Illuminate\Support\Facades\DB;

class DeleteRole extends Service
{
    use RoleHelpers;

    public function handle(Role $role): void
    {
        if ($role->isProtected()) {
            abort(422, 'Built-in roles cannot be deleted.');
        }

        DB::transaction(function () use ($role): void {
            $this->reassignHolders($role);

            $role->permissions()->detach();
            $role->users()->detach();

            $role->delete();
        });

        $this->forgetPermissionCache();
    }
}
