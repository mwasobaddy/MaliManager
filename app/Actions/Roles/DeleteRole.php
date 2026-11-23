<?php

namespace App\Actions\Roles;

use App\Actions\Roles\Concerns\RoleHelpers;
use App\Models\Role;
use App\Services\Service;

class DeleteRole extends Service
{
    use RoleHelpers;

    /**
     * Trash a custom role, keeping its users and permissions attached so
     * restore brings the whole thing back. Holders lose effective access
     * while it sits in the trash — spatie resolves through scoped queries,
     * which exclude soft-deleted roles — and the permission cache is
     * flushed so the loss (and a later restore) takes effect immediately.
     */
    public function handle(Role $role): void
    {
        if ($role->isProtected()) {
            abort(422, 'Built-in roles cannot be deleted.');
        }

        $this->transaction(function () use ($role): void {
            $this->delete($role);
        });

        $this->forgetPermissionCache();
    }

    /**
     * Permanently remove a trashed role, handing its users to `searcher`
     * first so nobody is left with no role at all. This is the old
     * destroy behaviour, deferred to the moment the row truly goes away.
     */
    public function force(Role $role): void
    {
        if ($role->isProtected()) {
            abort(422, 'Built-in roles cannot be deleted.');
        }

        $this->transaction(function () use ($role): void {
            $this->reassignHolders($role);

            $role->permissions()->detach();
            $role->users()->detach();

            $this->delete($role, force: true);
        });

        $this->forgetPermissionCache();
    }
}
