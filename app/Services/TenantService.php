<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DefaultSubRoles;
use Illuminate\Support\Str;

/**
 * Creates and tears down an organization's tenancy in single-database mode.
 * Each organization gets: a tenant registry row (uuid), a business profile,
 * default sub-roles, and the owning user's membership.
 */
class TenantService extends Service
{
    public function createOrganization(
        User $owner,
        string $name,
        Plan $plan,
        ?string $email = null,
        ?string $phone = null,
    ): Organization {
        return $this->transaction(function () use ($owner, $name, $plan, $email, $phone) {
            $tenant = $this->save(new Tenant(['id' => (string) Str::uuid()]));

            $organization = $this->save(new Organization([
                'tenant_id' => $tenant->getTenantKey(),
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'email' => $email,
                'phone' => $phone,
                'status' => 'active',
                'plan_id' => $plan->id,
                'created_by' => $owner->id,
            ]));

            $tenant->domains()->create([
                'domain' => $organization->slug.'.'.config('tenancy.subdomain_base'),
            ]);

            DefaultSubRoles::createFor($organization, $owner->id);

            $organization->users()->attach($owner->id, [
                'is_owner' => true,
                'status' => 'active',
            ]);

            $owner->assignRole('organization-owner');

            return $organization;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.($suffix++);
        }

        return $slug;
    }
}
