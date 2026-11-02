<?php

declare(strict_types=1);

namespace App\Actions\SubscriptionPayments;

use App\Models\SubscriptionPayment;
use App\Services\Service;

/**
 * Applies validated changes to an existing SubscriptionPayment. Kept as an Action so the
 * controller stops persisting, matching the create path in the same domain.
 */
class UpdateSubscriptionPayment extends Service
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(SubscriptionPayment $subscriptionPayment, array $data): SubscriptionPayment
    {
        $subscriptionPayment->update($data);

        return $subscriptionPayment;
    }
}
