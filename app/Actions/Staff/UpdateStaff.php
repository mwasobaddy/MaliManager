<?php

namespace App\Actions\Staff;

use App\Actions\Staff\Concerns\StaffHelpers;
use App\Models\Organization;
use App\Models\User;
use App\Services\Service;
use App\Services\StaffService;

class UpdateStaff extends Service
{
    use StaffHelpers;

    public function handle(Organization $organization, User $staff, User $actor, array $data): User
    {
        return $this->transaction(function () use ($organization, $staff, $actor, $data) {
            $staff->fill([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
            ])->save();

            $membership = app(StaffService::class)->membership($organization, $staff);

            $membership->fill([
                'sub_role_id' => $data['sub_role_id'],
                'status' => 'active',
            ]);

            $this->save($membership);

            $this->syncPropertyDelegations($membership, $data['property_ids'] ?? [], $actor);

            return $staff;
        });
    }
}
