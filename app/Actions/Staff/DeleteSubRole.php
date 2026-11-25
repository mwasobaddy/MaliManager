<?php

namespace App\Actions\Staff;

use App\Models\Organization;
use App\Models\SubRole;
use App\Models\User;
use App\Services\Service;

/**
 * Deletes an empty sub-role. A role with holders cannot go away silently:
 * nulling their role would strip permissions without the landlord noticing,
 * so the landlord reassigns them first.
 */
class DeleteSubRole extends Service
{
    public function handle(User $actor, Organization $organization, SubRole $subRole): void
    {
        abort_unless($subRole->organization_id === $organization->id, 404);
        abort_if($subRole->slug === 'landlord', 422, 'The landlord role cannot be deleted.');

        $holders = $subRole->members()->count();

        abort_if($holders > 0, 422, "Reassign the {$holders} staff member(s) holding this role first.");

        $this->transaction(function () use ($subRole): void {
            $subRole->subPermissions()->detach();

            $this->delete($subRole);
        });
    }
}
