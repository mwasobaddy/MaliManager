<?php

declare(strict_types=1);

namespace App\Actions\SubscriptionPayments;

use App\Actions\Billing\Concerns\RenewsSubscriptions;
use App\Models\Organization;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Service;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Creates a SubscriptionPayment from already-validated input. The Form Request owns the rules;
 * this owns the row so the controller stops persisting.
 *
 * Recording a payment for an organization also extends its subscription
 * by one month: a manual entry for money received offline must unlock
 * the plan exactly like a gateway payment does.
 */
class CreateSubscriptionPayment extends Service
{
    use RenewsSubscriptions;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?User $actor): SubscriptionPayment
    {
        return $this->transaction(function () use ($data, $actor) {
            $payment = SubscriptionPayment::create([...$data, 'created_by' => $actor?->id]);

            $organization = isset($data['organization_id'])
                ? Organization::find($data['organization_id'])
                : null;

            if ($organization !== null) {
                $periodStart = isset($data['period_start'])
                    ? Carbon::parse($data['period_start'])
                    : null;

                $this->renewSubscription($organization, $periodStart ?? $this->receivedOn($data));
            }

            return $payment;
        });
    }

    private function receivedOn(array $data): CarbonInterface
    {
        return isset($data['received_on'])
            ? Carbon::parse($data['received_on'])
            : now();
    }
}
