<?php

namespace App\Actions\Tenants;

use App\Actions\Tenants\Concerns\OrganizationHelpers;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Service;
use App\Support\DefaultSubRoles;
use Illuminate\Support\Str;

class CreateOrganization extends Service
{
    use OrganizationHelpers;

    public function handle(User $owner,
        string $name,
        Plan $plan,
        ?string $email = null,
        ?string $phone = null, ): Organization
    {
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
                // Paid plans start with a one-month free trial: full limits
                // now, payment only when value is proven. Free plans need
                // no trial; their limits always apply.
                'trial_ends_at' => $plan->price > 0 ? now()->addMonth() : null,
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
}
