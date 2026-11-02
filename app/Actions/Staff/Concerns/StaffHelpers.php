<?php

namespace App\Actions\Staff\Concerns;

use App\Models\Delegation;
use App\Models\OrganizationUser;
use App\Models\Property;
use App\Models\User;

trait StaffHelpers
{
    private function deletePropertyDelegations(OrganizationUser $membership): void
    {
        Delegation::where('organization_user_id', $membership->id)
            ->where('delegatable_type', Property::class)
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
}
