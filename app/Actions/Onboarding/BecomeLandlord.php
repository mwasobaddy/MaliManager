<?php

namespace App\Actions\Onboarding;

use App\Actions\Onboarding\Concerns\OnboardingHelpers;
use App\Data\Onboarding\BecomeLandlordData;
use App\Models\Organization;
use App\Models\User;
use App\Services\Service;
use App\Services\TenantService;

/**
 * Turns a guest into a landlord: provisions the organization and lands them on
 * first-asset setup.
 *
 * This is the relocated half of the old signup branch. The provisioning itself
 * is `resolveOrganization()` verbatim — new code path, same machinery.
 */
class BecomeLandlord extends Service
{
    use OnboardingHelpers;

    public function __construct(private TenantService $tenantService) {}

    public function handle(User $user, BecomeLandlordData $data): Organization
    {
        return $this->transaction(function () use ($user, $data): Organization {
            $organization = $this->resolveOrganization($user, null, $data);

            if ($data->listPublicly === true) {
                $settings = $organization->settings ?? [];
                $settings['list_publicly'] = true;
                $organization->update(['settings' => $settings]);
            }

            activity()->inLog('onboarding')
                ->causedBy($user)
                ->performedOn($organization)
                ->event('landlord.upgraded')
                ->log('Became a landlord');

            return $organization;
        });
    }
}
