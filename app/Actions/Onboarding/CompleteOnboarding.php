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
            $organization = $user->organizations()
                ->wherePivot('is_owner', true)
                ->first();

            $user->update([
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'password' => $validated['password'],
            ]);

            $isOrganization = ($validated['account_type'] ?? null) === 'organization'
                || $organization !== null;

            if ($isOrganization) {
                $organization = $this->resolveOrganization($user, $organization, $validated);
            } else {
                $this->createSearcherIdentity($user, $validated);
            }

            $user->markOnboarded();

            activity()->inLog('onboarding')
                ->causedBy($user)
                ->event('onboarding.completed')
                ->log('Completed onboarding');

            return $organization;
        });
    }
}
