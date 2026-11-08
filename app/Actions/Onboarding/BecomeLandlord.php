<?php

namespace App\Actions\Onboarding;

use App\Actions\Onboarding\Concerns\OnboardingHelpers;
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

    /**
     * @param  array{organization_name: string, currency: string, plan_slug: string, list_publicly?: bool}  $validated
     */
    public function handle(User $user, array $validated): Organization
    {
        return $this->transaction(function () use ($user, $validated): Organization {
            $organization = $this->resolveOrganization($user, null, $validated);

            if (($validated['list_publicly'] ?? false) === true) {
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
