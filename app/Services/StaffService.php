<?php

namespace App\Services;

use App\Models\Delegation;
use App\Models\LandParcel;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Manages staff members within an organization. A staff member is a
 * non-owner user with an organization membership (sub-role) and
 * delegations to the properties they manage. All writes are atomic.
 */
class StaffService extends Service
{
    /**
     * Create (or attach) a staff member and assign properties.
     */
    public function create(Organization $organization, User $actor, array $data): User
    {
        return $this->transaction(function () use ($organization, $actor, $data) {
            $user = $this->findOrCreateUser($data);

            $membership = OrganizationUser::firstOrNew([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ]);

            $membership->fill([
                'sub_role_id' => $data['sub_role_id'],
                'is_owner' => false,
                'status' => 'active',
                'created_by' => $actor->id,
            ]);

            $this->save($membership);

            $this->syncPropertyDelegations($membership, $data['property_ids'] ?? [], $actor);

            return $user;
        });
    }

    /**
     * Update a staff member's profile, role, and property assignments.
     */
    public function update(Organization $organization, User $staff, User $actor, array $data): User
    {
        return $this->transaction(function () use ($organization, $staff, $actor, $data) {
            $staff->fill([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
            ])->save();

            $membership = $this->membership($organization, $staff);

            $membership->fill([
                'sub_role_id' => $data['sub_role_id'],
                'status' => 'active',
            ]);

            $this->save($membership);

            $this->syncPropertyDelegations($membership, $data['property_ids'] ?? [], $actor);

            return $staff;
        });
    }

    /**
     * Soft-delete a staff member and their memberships/delegations.
     */
    public function softDelete(Organization $organization, User $staff, User $actor): void
    {
        $this->transaction(function () use ($organization, $staff) {
            $membership = $this->membership($organization, $staff);

            if ($membership) {
                $this->delete($membership);
                $this->deletePropertyDelegations($membership);
            }

            if ($staff->organizations()->count() <= 1) {
                $this->delete($staff);
            }
        });
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

    private function findOrCreateUser(array $data): User
    {
        $user = User::where('email', $data['email'])->first();

        if ($user) {
            $user->fill([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'onboarded_at' => $user->onboarded_at ?? now(),
            ])->save();

            return $user;
        }

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'onboarded_at' => now(),
            'status' => 'active',
        ]);

        return $this->save($user);
    }

    private function syncPropertyDelegations(OrganizationUser $membership, array $propertyIds, User $actor): void
    {
        $this->deletePropertyDelegations($membership);

        foreach (array_unique($propertyIds) as $propertyId) {
            $delegation = new Delegation([
                'organization_id' => $membership->organization_id,
                'organization_user_id' => $membership->id,
                'delegatable_type' => Property::class,
                'delegatable_id' => (int) $propertyId,
                'created_by' => $actor->id,
            ]);

            $this->save($delegation);
        }
    }

    private function deletePropertyDelegations(OrganizationUser $membership): void
    {
        Delegation::where('organization_user_id', $membership->id)
            ->where('delegatable_type', Property::class)
            ->get()
            ->each(fn (Delegation $delegation) => $this->delete($delegation));
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
