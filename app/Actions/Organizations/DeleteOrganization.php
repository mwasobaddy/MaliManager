<?php

namespace App\Actions\Organizations;

use App\Actions\Organizations\Concerns\OrganizationHelpers;
use App\Models\Organization;
use App\Models\User;
use App\Services\Service;
use App\Services\TenantService;

class DeleteOrganization extends Service
{
    use OrganizationHelpers;

    public function __construct(private TenantService $tenants) {}

    public function handle(Organization $organization, User $actor): void
    {
        activity()->performedOn($organization)->causedBy($actor)->log('Deleted organization '.$organization->name);

        $organization->delete();
    }
}
