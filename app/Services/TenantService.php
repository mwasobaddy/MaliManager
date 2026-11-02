<?php

namespace App\Services;

use App\Actions\Tenants\Concerns\OrganizationHelpers;
use App\Actions\Tenants\CreateOrganization;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;

/**
 * Creates and tears down an organization's tenancy in single-database mode.
 * Each organization gets: a tenant registry row (uuid), a business profile,
 * default sub-roles, and the owning user's membership.
 */
class TenantService extends Service
{
    use OrganizationHelpers;

    public function createOrganization(
        User $owner,
        string $name,
        Plan $plan,
        ?string $email = null,
        ?string $phone = null,
    ): Organization {
        return app(CreateOrganization::class)->handle($owner, $name, $plan, $email, $phone);
    }
}
