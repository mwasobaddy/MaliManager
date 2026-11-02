<?php

namespace App\Services;

use App\Actions\Onboarding\CompleteOnboarding;
use App\Actions\Onboarding\Concerns\OnboardingHelpers;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;

/**
 * Completes first-login onboarding. Branches on account type:
 * organization accounts get their org created (full tenancy provisioning
 * via {@see TenantService}) or updated; searcher accounts get a Person
 * identity and the Searcher platform role.
 */
class OnboardingService extends Service
{
    use OnboardingHelpers;

    public function __construct(private TenantService $tenantService) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function complete(User $user, array $validated): ?Organization
    {
        return app(CompleteOnboarding::class)->handle($user, $validated);
    }
}
