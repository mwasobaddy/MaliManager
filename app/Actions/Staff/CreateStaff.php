<?php

namespace App\Actions\Staff;

use App\Actions\Staff\Concerns\StaffHelpers;
use App\Enums\PlatformPermissionKey;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\Service;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

class CreateStaff extends Service
{
    use StaffHelpers;

    public function handle(Organization $organization, User $actor, array $data): User
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

            // Staff share the organization dashboard tab with owners. The
            // permission record is seeded centrally; if the spatie cache is
            // stale (e.g. during a fresh seed run) flush and retry once.
            $permission = PlatformPermissionKey::ViewOrgMetrics->value;

            try {
                $user->givePermissionTo($permission);
            } catch (PermissionDoesNotExist) {
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                $user->givePermissionTo($permission);
            }

            return $user;
        });
    }
}
