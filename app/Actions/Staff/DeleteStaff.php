<?php

namespace App\Actions\Staff;

use App\Actions\Staff\Concerns\StaffHelpers;
use App\Models\Organization;
use App\Models\User;
use App\Services\Service;
use App\Services\StaffService;

class DeleteStaff extends Service
{
    use StaffHelpers;

    public function handle(Organization $organization, User $staff, User $actor): void
    {
        $this->transaction(function () use ($organization, $staff) {
            $membership = app(StaffService::class)->membership($organization, $staff);

            if ($membership) {
                $this->delete($membership);
                $this->deletePropertyDelegations($membership);
            }

            if ($staff->organizations()->count() <= 1) {
                $this->delete($staff);
            }
        });
    }
}
