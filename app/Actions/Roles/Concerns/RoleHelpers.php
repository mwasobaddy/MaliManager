<?php

namespace App\Actions\Roles\Concerns;

use App\Enums\PlatformRole;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

trait RoleHelpers
{
    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Move every holder of a role to `searcher`.
     */
    private function reassignHolders(Role $role): void
    {
        $searcher = Role::where('name', PlatformRole::Searcher->value)->first();

        if (! $searcher) {
            abort(422, 'The searcher fallback role is missing.');
        }

        foreach ($role->users()->get() as $user) {
            // syncRoles replaces the whole set. Removing the doomed role first
            // and adding the fallback means a user holding several roles keeps
            // the others rather than being reset to just `searcher`.
            $user->removeRole($role);

            if (! $user->hasRole($searcher)) {
                $user->assignRole($searcher);
            }

            $this->reassigned[(int) $user->getKey()] = (int) $user->getKey();
        }
    }

    /**
     * Resolve permission names to models, skipping any that do not exist.
     *
     * @param  list<string>  $names
     * @return list<Permission>
     */
    private function resolvePermissions(array $names): array
    {
        if ($names === []) {
            return [];
        }

        return Permission::whereIn('name', $names)->get()->all();
    }
}
