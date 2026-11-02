<?php

declare(strict_types=1);

namespace App\Actions\SubscriptionPayments;

use App\Models\SubscriptionPayment;
use App\Services\Service;

/**
 * Removes a SubscriptionPayment. Authorization lives in the Form Request and the policy; this
 * owns the destructive call so no controller deletes directly.
 */
class DeleteSubscriptionPayment extends Service
{
    public function handle(SubscriptionPayment $subscriptionPayment): void
    {
        $subscriptionPayment->delete();
    }
}
