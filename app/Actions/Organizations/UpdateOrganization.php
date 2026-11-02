<?php

namespace App\Actions\Organizations;

use App\Actions\Organizations\Concerns\OrganizationHelpers;
use App\Models\Organization;
use App\Models\User;
use App\Services\Service;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;

class UpdateOrganization extends Service
{
    use OrganizationHelpers;

    public function __construct(private TenantService $tenants) {}

    public function handle(Organization $organization, array $data, User $actor): Organization
    {
        return DB::transaction(function () use ($organization, $data, $actor): Organization {
            $organization->fill([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'status' => $data['status'],
                'plan_id' => $data['plan_id'],
            ])->save();

            activity()->performedOn($organization)->causedBy($actor)->log('Updated organization '.$organization->name);

            return $organization;
        });
    }
}
