<?php

namespace App\Services;

use App\Actions\Staff\Concerns\StaffHelpers;
use App\Actions\Staff\CreateStaff;
use App\Actions\Staff\DeleteStaff;
use App\Actions\Staff\UpdateStaff;
use App\Models\Delegation;
use App\Models\LandParcel;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Property;
use App\Models\SubRole;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Manages staff members within an organization. A staff member is a
 * non-owner user with an organization membership (sub-role) and
 * delegations to the properties they manage. All writes are atomic.
 */
class StaffService extends Service
{
    use StaffHelpers;

    /**
     * Create (or attach) a staff member and assign properties.
     */
    public function create(Organization $organization, User $actor, array $data): User
    {
        return app(CreateStaff::class)->handle($organization, $actor, $data);
    }

    /**
     * Dropdown data for the staff form, shared by the staff pages and the
     * setup funnel so both offer the same roles and properties.
     *
     * @return array{sub_roles: array<int, array{id: int, name: string}>, properties: array<int, array{id: int, name: string, slug: string}>}
     */
    public function formOptions(Organization $organization): array
    {
        return [
            'sub_roles' => SubRole::where('organization_id', $organization->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (SubRole $role) => ['id' => $role->id, 'name' => $role->name])
                ->all(),
            'properties' => Property::where('organization_id', $organization->id)
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (Property $property) => ['id' => $property->id, 'name' => $property->name, 'slug' => $property->slug])
                ->all(),
        ];
    }

    /**
     * Update a staff member's profile, role, and property assignments.
     */
    public function update(Organization $organization, User $staff, User $actor, array $data): User
    {
        return app(UpdateStaff::class)->handle($organization, $staff, $actor, $data);
    }

    /**
     * Soft-delete a staff member and their memberships/delegations.
     */
    public function softDelete(Organization $organization, User $staff, User $actor): void
    {
        app(DeleteStaff::class)->handle($organization, $staff, $actor);
    }

    /**
     * The organization membership for a staff member, if any.
     */
    public function membership(Organization $organization, User $user): ?OrganizationUser
    {
        return OrganizationUser::where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * The properties a staff member manages within the organization.
     *
     * @return Collection<int, Property>
     */
    public function managedProperties(Organization $organization, User $user): Collection
    {
        $membership = $this->membership($organization, $user);

        if (! $membership) {
            return collect();
        }

        return Property::query()
            ->whereIn('id', $this->delegatedPropertyIds($membership))
            ->get();
    }

    /**
     * The ids of properties a staff member is delegated to.
     *
     * @return array<int, int>
     */
    public function delegatedPropertyIds(?OrganizationUser $membership): array
    {
        if (! $membership) {
            return [];
        }

        return Delegation::where('organization_user_id', $membership->id)
            ->where('delegatable_type', Property::class)
            ->pluck('delegatable_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The ids of land parcels a staff member is delegated to.
     *
     * @return array<int, int>
     */
    public function delegatedLandParcelIds(?OrganizationUser $membership): array
    {
        if (! $membership) {
            return [];
        }

        return Delegation::where('organization_user_id', $membership->id)
            ->where('delegatable_type', LandParcel::class)
            ->pluck('delegatable_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
