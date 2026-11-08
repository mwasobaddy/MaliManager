<?php

namespace App\Actions\Onboarding;

use App\Actions\Onboarding\Concerns\OnboardingHelpers;
use App\Models\Organization;
use App\Models\User;
use App\Services\Service;
use App\Services\TenantService;

class CompleteOnboarding extends Service
{
    use OnboardingHelpers;

    public function __construct(private TenantService $tenantService) {}

    public function handle(User $user, array $validated): ?Organization
    {
        return $this->transaction(function () use ($user, $validated) {
            $user->update([
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'password' => $validated['password'],
            ]);

            // Guest-minimal by design: no org, no role, no Person row. The
            // organization half of the old branch relocated to `BecomeLandlord`;
            // the tenant identity is created lazily on first tenant action.
            $user->markOnboarded();

            activity()->inLog('onboarding')
                ->causedBy($user)
                ->event('onboarding.completed')
                ->log('Completed onboarding');

            return null;
        });
    }
}
