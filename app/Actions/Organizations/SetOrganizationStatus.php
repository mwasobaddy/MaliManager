<?php

namespace App\Actions\Organizations;

use App\Actions\Organizations\Concerns\OrganizationHelpers;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Services\Service;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;

class SetOrganizationStatus extends Service
{
    use OrganizationHelpers;

    public function __construct(private TenantService $tenants) {}

    public function handle(Organization $organization, OrganizationStatus $status, User $actor): Organization
    {
        return DB::transaction(function () use ($organization, $status, $actor): Organization {
            $organization->forceFill(['status' => $status])->save();

            if ($status === OrganizationStatus::Suspended) {
                $memberIds = $organization->users()->pluck('users.id');

                DB::table('sessions')->whereIn('user_id', $memberIds)->delete();
            }

            activity()
                ->performedOn($organization)
                ->causedBy($actor)
                ->log($status === OrganizationStatus::Suspended
                    ? 'Suspended organization '.$organization->name
                    : 'Reactivated organization '.$organization->name);

            return $organization;
        });
    }
}
