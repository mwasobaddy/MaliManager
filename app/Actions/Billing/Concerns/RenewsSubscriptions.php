<?php

namespace App\Actions\Billing\Concerns;

use App\Models\Organization;
use Carbon\CarbonInterface;

/**
 * Shared by every action that completes a payment: gateway callbacks and
 * the platform's manual payment entry all extend the subscription the
 * same way. One recorded payment buys one month from the later of now
 * (or the payment period) and the current expiry — renewing mid-cycle
 * stacks instead of overwriting.
 */
trait RenewsSubscriptions
{
    private function renewSubscription(Organization $organization, ?CarbonInterface $periodStart = null, int $months = 1): void
    {
        $base = $organization->subscription_ends_at;

        $start = $periodStart ?? now();

        if ($base === null || $start->greaterThan($base)) {
            $base = $start;
        }

        $this->save($organization->fill([
            'subscription_ends_at' => $base->copy()->addMonths($months),
        ]));
    }
}
