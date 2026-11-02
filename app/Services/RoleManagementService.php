<?php

namespace App\Services;

use App\Actions\Roles\Concerns\RoleHelpers;
use App\Actions\Roles\CreateRole;
use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\SetRoleActive;
use App\Models\Role;
use App\Models\User;

/**
 * The lifecycle of a *custom* platform role.
 *
 * Built-in roles are excluded throughout: they cannot be created, deactivated
 * or deleted, because `admin` gates access to the platform at all and the other
 * three are referenced by name from organization setup and the user role picker.
 *
 * Every operation that strips a role from its holders also moves those holders
 * to `searcher`, so nobody is left with no role and therefore no permissions at
 * all. `searcher` is the right destination because it is the least privileged
 * built-in: a mistake here reduces access rather than widening it.
 */
class RoleManagementService
{
    use RoleHelpers;

    /**
     * Users moved off a role, keyed by user id.
     *
     * @var array<int, int>
     */
    private array $reassigned = [];

    /**
     * Create a custom role with its permissions.
     *
     * `isActive` is a parameter rather than something the caller sets afterwards:
     * an imported row that says "inactive" has to be written as one insert, not
     * created and then flipped, or a crash between the two leaves an active role
     * the file asked to be inactive.
     *
     * @param  list<string>  $permissionNames
     */
    public function create(string $name, array $permissionNames, bool $isActive = true): Role
    {
        return app(CreateRole::class)->handle($name, $permissionNames, $isActive);
    }

    /**
     * Activate or deactivate a custom role.
     *
     * Deactivating moves the holders to `searcher` for the same reason deleting
     * does: an inactive role still reads as "assigned" to `hasRole()`, so a user
     * left holding one looks like they have a role while actually having none of
     * its permissions. Moving them keeps the role list and the effective
     * permissions telling the same story.
     */
    public function setActive(Role $role, bool $isActive): void
    {
        app(SetRoleActive::class)->handle($role, $isActive);
    }

    /**
     * Delete a custom role, handing its users to `searcher` first.
     *
     * Spatie does not cascade on delete, so the pivots are cleared explicitly.
     * Leaving them would leave `model_has_roles` pointing at a role id that no
     * longer exists, and `hasRole()` would go quiet about a role that is gone.
     */
    public function delete(Role $role): void
    {
        app(DeleteRole::class)->handle($role);
    }

    /**
     * Users moved off a role by the most recent operation, for auditing.
     *
     * @return array<int, int>
     */
    public function reassignedUserIds(): array
    {
        return $this->reassigned;
    }
}
