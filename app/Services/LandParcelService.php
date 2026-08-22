<?php

namespace App\Services;

use App\Models\Delegation;
use App\Models\LandParcel;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;

/**
 * Manages land parcels within an organization. A parcel is a standalone
 * plot (title deed, acreage, zoning) that staff can be delegated to
 * manage, mirroring property delegation. All writes are atomic.
 */
class LandParcelService extends Service
{
    /**
     * Create a land parcel and sync its manager delegations.
     */
    public function create(Organization $organization, User $actor, array $data): LandParcel
    {
        return $this->transaction(function () use ($organization, $actor, $data) {
            $parcel = $this->save(new LandParcel([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'slug' => LandParcel::uniqueSlug($organization->id, $data['name']),
                'title_deed_number' => $data['title_deed_number'] ?? null,
                'acreage' => $data['acreage'] ?? null,
                'zoning' => $data['zoning'] ?? 'residential',
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'status' => $data['status'] ?? 'active',
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'available_for_lease' => $data['available_for_lease'] ?? false,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]));

            $this->syncDelegations($parcel, $data['manager_ids'] ?? [], $actor);

            return $parcel;
        });
    }

    /**
     * Update a land parcel's details and manager delegations.
     */
    public function update(LandParcel $parcel, User $actor, array $data): LandParcel
    {
        return $this->transaction(function () use ($parcel, $actor, $data) {
            $parcel->fill([
                'name' => $data['name'],
                'title_deed_number' => $data['title_deed_number'] ?? null,
                'acreage' => $data['acreage'] ?? null,
                'zoning' => $data['zoning'] ?? $parcel->zoning,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'status' => $data['status'] ?? $parcel->status,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'available_for_lease' => $data['available_for_lease'] ?? false,
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->syncDelegations($parcel, $data['manager_ids'] ?? [], $actor);

            return $parcel;
        });
    }

    /**
     * Soft-delete a parcel; delegated managers lose access automatically
     * since the delegations are deleted with the parcel.
     */
    public function softDelete(LandParcel $parcel): void
    {
        $this->transaction(function () use ($parcel) {
            Delegation::where('delegatable_type', LandParcel::class)
                ->where('delegatable_id', $parcel->id)
                ->delete();

            $this->delete($parcel);
        });
    }

    /**
     * The organization members delegated to manage a parcel, as
     * OrganizationUser ids.
     *
     * @return array<int, int>
     */
    public function managerIds(LandParcel $parcel): array
    {
        return Delegation::where('delegatable_type', LandParcel::class)
            ->where('delegatable_id', $parcel->id)
            ->pluck('organization_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The user ids delegated to manage a parcel (used to pre-select the
     * manager checkboxes on the edit form).
     *
     * @return array<int, int>
     */
    public function managerUserIds(LandParcel $parcel): array
    {
        $membershipIds = Delegation::where('delegatable_type', LandParcel::class)
            ->where('delegatable_id', $parcel->id)
            ->pluck('organization_user_id')
            ->all();

        if ($membershipIds === []) {
            return [];
        }

        return OrganizationUser::whereIn('id', $membershipIds)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Sync a parcel's manager delegations by replacing them. The given
     * ids are user ids from the form; they are resolved to the
     * OrganizationUser membership ids required by the Delegation model.
     *
     * @param  array<int, int>  $userIds
     */
    private function syncDelegations(LandParcel $parcel, array $userIds, User $actor): void
    {
        Delegation::where('delegatable_type', LandParcel::class)
            ->where('delegatable_id', $parcel->id)
            ->delete();

        $userIds = array_unique(array_filter($userIds, fn ($id) => $id !== null));

        if ($userIds === []) {
            return;
        }

        $membershipIds = OrganizationUser::where('organization_id', $parcel->organization_id)
            ->whereIn('user_id', $userIds)
            ->pluck('id')
            ->all();

        foreach ($membershipIds as $membershipId) {
            $this->save(new Delegation([
                'organization_id' => $parcel->organization_id,
                'organization_user_id' => $membershipId,
                'delegatable_type' => LandParcel::class,
                'delegatable_id' => $parcel->id,
                'created_by' => $actor->id,
            ]));
        }
    }
}
