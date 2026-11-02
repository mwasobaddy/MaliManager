<?php

namespace App\Services;

use App\Actions\LandParcels\Concerns\LandParcelHelpers;
use App\Actions\LandParcels\CreateLandParcel;
use App\Actions\LandParcels\DeleteLandParcel;
use App\Actions\LandParcels\UpdateLandParcel;
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
    use LandParcelHelpers;

    /**
     * Create a land parcel and sync its manager delegations.
     */
    public function create(Organization $organization, User $actor, array $data): LandParcel
    {
        return app(CreateLandParcel::class)->handle($organization, $actor, $data);
    }

    /**
     * Update a land parcel's details and manager delegations.
     */
    public function update(LandParcel $parcel, User $actor, array $data): LandParcel
    {
        return app(UpdateLandParcel::class)->handle($parcel, $actor, $data);
    }

    /**
     * Soft-delete a parcel; delegated managers lose access automatically
     * since the delegations are deleted with the parcel.
     */
    public function softDelete(LandParcel $parcel): void
    {
        app(DeleteLandParcel::class)->handle($parcel);
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
}
