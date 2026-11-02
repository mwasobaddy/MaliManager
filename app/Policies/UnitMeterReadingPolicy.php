<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformRole;
use App\Enums\SubPermissionKey;
use App\Models\Organization;
use App\Models\UnitMeterReading;
use App\Models\User;
use App\Support\TenancyContext;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Meter readings are organization-scoped records staff manage through the
 * `unit.manage` sub-permission. Viewing is any staff member in the org;
 * recording is the same set, because a caretaker reading a meter is exactly
 * the persona this feature exists for.
 */
class UnitMeterReadingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        $organization = $this->organization();

        return $organization !== null && $this->belongsTo($user, $organization);
    }

    public function view(User $user, UnitMeterReading $reading): bool
    {
        return $user->hasRole(PlatformRole::Admin->value)
            || $this->canManageIn($user, $reading->organization_id);
    }

    /**
     * Called from the Form Request as `can('create', UnitMeterReading::class)`,
     * so there is no model instance to read an organization from.
     */
    public function create(User $user): bool
    {
        $organization = $this->organization();

        return $organization !== null && $this->canManageIn($user, $organization->id);
    }

    public function delete(User $user, UnitMeterReading $reading): bool
    {
        return $this->canManageIn($user, $reading->organization_id);
    }

    private function canManageIn(User $user, int $organizationId): bool
    {
        $organization = Organization::find($organizationId);

        if (! $organization) {
            return false;
        }

        return $user->isOwnerOf($organization)
            || $user->hasSubPermission($organization, SubPermissionKey::UnitManage);
    }

    private function belongsTo(User $user, Organization $organization): bool
    {
        return $user->isOwnerOf($organization)
            || $user->hasSubPermission($organization, SubPermissionKey::UnitManage);
    }

    private function organization(): ?Organization
    {
        return TenancyContext::organization();
    }
}
