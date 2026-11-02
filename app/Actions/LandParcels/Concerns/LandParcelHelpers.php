<?php

namespace App\Actions\LandParcels\Concerns;

use App\Models\Delegation;
use App\Models\LandParcel;
use App\Models\OrganizationUser;
use App\Models\User;

trait LandParcelHelpers
{
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
