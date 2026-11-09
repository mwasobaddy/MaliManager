<?php

namespace App\Actions\Onboarding\Concerns;

use App\Data\Onboarding\BecomeLandlordData;
use App\Enums\PlatformRole;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait OnboardingHelpers
{
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

    /**
     * Update the existing organization or provision a brand-new one. New
     * organizations start on the free plan; the paid plan choice (and its
     * trial) happens in the Plan & trial funnel step, not here.
     */
    private function resolveOrganization(User $user, ?Organization $organization, BecomeLandlordData $data): Organization
    {
        if ($organization) {
            $organization->update([
                'name' => $data->organizationName,
                'settings' => array_merge($organization->settings ?? [], [
                    'currency' => $data->currency,
                ]),
            ]);

            return $organization;
        }

        $organization = $this->tenantService->createOrganization(
            owner: $user,
            name: $data->organizationName,
            plan: Plan::free(),
            email: $user->email,
            phone: $user->phone,
        );

        $organization->update([
            'settings' => [
                'currency' => $data->currency,
            ],
        ]);

        return $organization;
    }
}
