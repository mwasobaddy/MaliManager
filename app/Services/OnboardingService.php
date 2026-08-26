<?php

namespace App\Services;

use App\Enums\PlatformRole;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Completes first-login onboarding. Branches on account type:
 * organization accounts get their org created (full tenancy provisioning
 * via {@see TenantService}) or updated; searcher accounts get a Person
 * identity and the Searcher platform role.
 */
class OnboardingService extends Service
{
    public function __construct(private TenantService $tenantService) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function complete(User $user, array $validated): ?Organization
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

    /**
     * Update the existing organization or provision a brand-new one.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveOrganization(User $user, ?Organization $organization, array $validated): Organization
    {
        $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

        if ($organization) {
            $organization->update([
                'name' => $validated['organization_name'],
                'plan_id' => $plan->id,
                'settings' => array_merge($organization->settings ?? [], [
                    'currency' => $validated['currency'],
                ]),
            ]);

            return $organization;
        }

        $organization = $this->tenantService->createOrganization(
            owner: $user,
            name: $validated['organization_name'],
            plan: $plan,
            email: $user->email,
            phone: $validated['phone'] ?? null,
        );

        $organization->update([
            'settings' => [
                'currency' => $validated['currency'],
            ],
        ]);

        return $organization;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createSearcherIdentity(User $user, array $validated): void
    {
        DB::transaction(function () use ($user, $validated): void {
            $person = Person::create([
                'first_name' => $validated['name'],
                'email' => $user->email,
                'phone' => $validated['phone'] ?? null,
                'status' => 'active',
                'created_by' => $user->id,
            ]);

            $user->update([
                'person_id' => $person->id,
                'created_by' => $user->id,
            ]);

            $user->assignRole(PlatformRole::Searcher->value);
        });
    }
}
