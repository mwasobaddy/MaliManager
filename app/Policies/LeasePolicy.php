<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformRole;
use App\Enums\SubPermissionKey;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LeasePolicy
{
    use HandlesAuthorization;

    /**
     * A searcher sees only their own leases; org staff see leases within
     * their organization; the platform admin sees everything.
     */
    public function view(User $user, Lease $lease): bool
    {
        if ($user->person_id && $user->person_id === $lease->person_id) {
            return true;
        }

        if ($user->hasRole(PlatformRole::Admin->value)) {
            return true;
        }

        return $this->canManageIn($user, $lease->organization_id);
    }

    public function create(User $user, $organization = null): bool
    {
        $id = $organization instanceof Organization
            ? $organization->id
            : (is_int($organization) ? $organization : null);

        return $id ? $this->canManageIn($user, $id) : false;
    }

    public function update(User $user, Lease $lease): bool
    {
        return $this->canManageIn($user, $lease->organization_id);
    }

    public function delete(User $user, Lease $lease): bool
    {
        return $this->canManageIn($user, $lease->organization_id);
    }

    private function canManageIn(User $user, int $organizationId): bool
    {
        $organization = Organization::find($organizationId);

        if (! $organization) {
            return false;
        }

        return $user->isOwnerOf($organization)
            || $user->hasSubPermission($organization, SubPermissionKey::OccupantManage);
    }
}
