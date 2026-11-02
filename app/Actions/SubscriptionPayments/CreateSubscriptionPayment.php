<?php

declare(strict_types=1);

namespace App\Actions\SubscriptionPayments;

use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Service;

/**
 * Creates a SubscriptionPayment from already-validated input. The Form Request owns the rules;
 * this owns the row so the controller stops persisting.
 */
class CreateSubscriptionPayment extends Service
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?User $actor): SubscriptionPayment
    {
        return SubscriptionPayment::create([...$data, 'created_by' => $actor?->id]);
    }
}
