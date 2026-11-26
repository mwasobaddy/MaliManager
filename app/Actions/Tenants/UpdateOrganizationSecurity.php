<?php

namespace App\Actions\Tenants;

use App\Models\Organization;
use App\Models\User;
use App\Services\Service;

/**
 * Updates an organization's security policy. Owner-only: a staff member
 * must never be able to relax the policy that constrains them.
 *
 * @param  array{require_staff_two_factor?: bool}  $data
 */
class UpdateOrganizationSecurity extends Service
{
    public function handle(User $actor, Organization $organization, array $data): Organization
    {
        abort_unless($actor->isOwnerOf($organization) || $actor->isPlatformAdmin(), 403);

        return $this->transaction(function () use ($actor, $organization, $data): Organization {
            $settings = $organization->settings ?? [];
            $settings['require_staff_two_factor'] = (bool) ($data['require_staff_two_factor'] ?? false);
            $organization->settings = $settings;
            $this->save($organization);

            activity()->inLog('auth')
                ->causedBy($actor)
                ->performedOn($organization)
                ->event('organization.security_updated')
                ->withProperties(['require_staff_two_factor' => $organization->settings['require_staff_two_factor']])
                ->log('Updated organization security policy');

            return $organization->refresh();
        });
    }
}
