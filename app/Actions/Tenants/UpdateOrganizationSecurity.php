<?php

namespace App\Actions\Tenants;

use App\Models\Organization;
use App\Models\User;
use App\Services\Service;

/**
 * Updates an organization's security policy. Owner-only: a staff member
 * must never be able to relax the policy that constrains them.
 *
 * @param  array{require_staff_two_factor?: bool, mode?: string, dismiss_pro_suggestion?: bool}  $data
 */
class UpdateOrganizationSecurity extends Service
{
    public function handle(User $actor, Organization $organization, array $data): Organization
    {
        abort_unless($actor->isOwnerOf($organization) || $actor->isPlatformAdmin(), 403);

        return $this->transaction(function () use ($actor, $organization, $data): Organization {
            $settings = $organization->settings ?? [];

            if (array_key_exists('require_staff_two_factor', $data)) {
                $settings['require_staff_two_factor'] = (bool) $data['require_staff_two_factor'];
            }

            if (array_key_exists('mode', $data)) {
                $settings['mode'] = $data['mode'];
            }

            if (array_key_exists('dismiss_pro_suggestion', $data)) {
                $settings['mode_suggestion_dismissed'] = (bool) $data['dismiss_pro_suggestion'];
            }

            $organization->settings = $settings;
            $this->save($organization);

            activity()->inLog('auth')
                ->causedBy($actor)
                ->performedOn($organization)
                ->event('organization.security_updated')
                ->withProperties(array_intersect_key($settings, [
                    'require_staff_two_factor' => true,
                    'mode' => true,
                ]))
                ->log('Updated organization security policy');

            return $organization->refresh();
        });
    }
}
