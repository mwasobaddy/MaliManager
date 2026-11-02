<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformRole;
use App\Enums\SubPermissionKey;
use App\Models\DepositRefund;
use App\Models\Organization;
use App\Models\User;
use App\Support\TenancyContext;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Refunds are money leaving the organization, so requesting and deciding are
 * separate permissions: any staff member with `lease.manage` can raise one, but
 * only an owner or a `lease.edit` holder can approve it.
 */
class DepositRefundPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        $organization = $this->organization();

        return $organization !== null && $this->canRequestIn($user, $organization);
    }

    public function view(User $user, DepositRefund $refund): bool
    {
        return $user->hasRole(PlatformRole::Admin->value)
            || $this->canRequestIn($user, $this->findOrganization($refund));
    }

    public function create(User $user): bool
    {
        $organization = $this->organization();

        return $organization !== null && $this->canRequestIn($user, $organization);
    }

    /** Approve or reject. */
    public function decide(User $user, DepositRefund $refund): bool
    {
        $organization = $this->findOrganization($refund);

        if (! $organization) {
            return $user->hasRole(PlatformRole::Admin->value);
        }

        return $user->hasRole(PlatformRole::Admin->value)
            || $user->isOwnerOf($organization)
            || $user->hasSubPermission($organization, SubPermissionKey::LeaseEdit);
    }

    private function canRequestIn(User $user, ?Organization $organization): bool
    {
        if (! $organization) {
            return false;
        }

        return $user->isOwnerOf($organization)
            || $user->hasSubPermission($organization, SubPermissionKey::LeaseManage);
    }

    private function findOrganization(DepositRefund $refund): ?Organization
    {
        return Organization::find($refund->organization_id);
    }

    private function organization(): ?Organization
    {
        return TenancyContext::organization();
    }
}
