<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Central organization management. Wraps {@see TenantService} so admins
 * can create fully-provisioned organizations (tenant, domain, default
 * sub-roles, owner membership) and manage their lifecycle.
 */
class OrganizationManagementService extends Service
{
    public function __construct(private TenantService $tenants) {}

    public function create(User $owner, string $name, Plan $plan, ?string $email = null, ?string $phone = null): Organization
    {
        return $this->tenants->createOrganization($owner, $name, $plan, $email, $phone);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Organization $organization, array $data, User $actor): Organization
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

    public function deleteOrganization(Organization $organization, User $actor): void
    {
        activity()->performedOn($organization)->causedBy($actor)->log('Deleted organization '.$organization->name);

        $organization->delete();
    }
}
