<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformRole;
use App\Enums\SubPermissionKey;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestNote;
use App\Models\Organization;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Notes inherit their authorization from the request they hang off: if you may
 * work the request, you may note it. The organization is read from the parent
 * rather than the note, because the note has no organization column of its own.
 */
class MaintenanceRequestNotePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user, MaintenanceRequest $request): bool
    {
        return $this->canManageIn($user, $request->organization_id)
            && $this->delegatedToRequest($user, $request);
    }

    public function view(User $user, MaintenanceRequestNote $note): bool
    {
        return $this->canManageIn($user, $note->request->organization_id)
            && $this->delegatedToRequest($user, $note->request);
    }

    /**
     * Called as `can('create', [MaintenanceRequestNote::class, $request])`.
     */
    public function create(User $user, MaintenanceRequest $request): bool
    {
        return $this->canManageIn($user, $request->organization_id)
            && $this->delegatedToRequest($user, $request);
    }

    public function delete(User $user, MaintenanceRequestNote $note): bool
    {
        return $this->canManageIn($user, $note->request->organization_id)
            && $this->delegatedToRequest($user, $note->request);
    }

    private function canManageIn(User $user, ?int $organizationId): bool
    {
        if ($organizationId === null) {
            return false;
        }

        $organization = Organization::find($organizationId);

        if (! $organization) {
            return false;
        }

        return $user->isOwnerOf($organization)
            || $user->hasRole(PlatformRole::Admin->value)
            || $user->hasSubPermission($organization, SubPermissionKey::MaintenanceManage)
            || $user->hasSubPermission($organization, SubPermissionKey::MaintenanceEdit);
    }

    /**
     * Staff only note requests on properties they are delegated to; owners
     * and admins pass through the callers' own checks.
     */
    private function delegatedToRequest(User $user, MaintenanceRequest $request): bool
    {
        $organization = Organization::find($request->organization_id);

        if (! $organization
            || $user->isOwnerOf($organization)
            || $user->hasRole(PlatformRole::Admin->value)
        ) {
            return (bool) $organization;
        }

        if ($request->property_id === null) {
            return false;
        }

        return in_array(
            $request->property_id,
            app(StaffService::class)->delegatedPropertyIds($user->membershipFor($organization)),
            true,
        );
    }
}
