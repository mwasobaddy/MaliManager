<?php

namespace App\Actions\Staff\Concerns;

use App\Models\Branch;
use App\Models\Delegation;
use App\Models\OrganizationUser;
use App\Models\Property;
use App\Models\User;

trait StaffHelpers
{
    private function deleteDelegations(OrganizationUser $membership): void
    {
        Delegation::where('organization_user_id', $membership->id)
            ->whereIn('delegatable_type', [Property::class, Branch::class])
            ->get()
            ->each(fn (Delegation $delegation) => $this->delete($delegation));
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
        $this->deleteDelegations($membership);

        foreach (array_unique($propertyIds) as $propertyId) {
            // Restoring beats re-inserting: the unique index counts
            // soft-deleted rows, so a blind insert blows up the second
            // time a staffer is assigned to the same property.
            $this->syncDelegation($membership, Property::class, (int) $propertyId, $actor);
        }
    }

    /**
     * A branch delegation covers every current and future property and land
     * parcel in that branch: access resolution expands it, so assigning a
     * branch manager is one row, not one row per asset.
     */
    private function syncBranchDelegations(OrganizationUser $membership, array $branchIds, User $actor): void
    {
        foreach (array_unique($branchIds) as $branchId) {
            $this->syncDelegation($membership, Branch::class, (int) $branchId, $actor);
        }
    }

    private function syncDelegation(OrganizationUser $membership, string $type, int $id, User $actor): void
    {
        $delegation = Delegation::withTrashed()->firstOrNew([
            'organization_user_id' => $membership->id,
            'delegatable_type' => $type,
            'delegatable_id' => $id,
        ]);

        if ($delegation->exists) {
            if ($delegation->trashed()) {
                $delegation->restore();
            }

            return;
        }

        $delegation->fill([
            'organization_id' => $membership->organization_id,
            'created_by' => $actor->id,
        ]);

        $this->save($delegation);
    }
}
