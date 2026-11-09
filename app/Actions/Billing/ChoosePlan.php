<?php

namespace App\Actions\Billing;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\Service;

/**
 * Confirms the organization's plan in the Plan & trial funnel step and
 * records the owner's notification channel. A paid plan starts the
 * one-month free trial — but only when none was started and no
 * subscription is active, so re-confirming (or switching plans) can
 * never restart the trial.
 */
class ChoosePlan extends Service
{
    public function handle(Organization $organization, User $user, Plan $plan, string $channel, ?string $phone = null): Organization
    {
        return $this->transaction(function () use ($organization, $user, $plan, $channel, $phone) {
            $attributes = [
                'plan_id' => $plan->id,
                'plan_confirmed_at' => now(),
            ];

            if ($plan->price > 0
                && $organization->trial_ends_at === null
                && ! $organization->hasActiveSubscription()) {
                $attributes['trial_ends_at'] = now()->addMonth();
            }

            $this->save($organization->fill($attributes));

            $user->notification_channel = $channel;

            if ($phone !== null && $phone !== '') {
                $user->phone = $phone;
            }

            $this->save($user);

            return $organization->refresh();
        });
    }
}
