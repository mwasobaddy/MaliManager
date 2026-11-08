<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\RenewsSubscriptions;
use App\Actions\SubscriptionPayments\CreateSubscriptionPayment;
use App\Models\PaymentAttempt;
use App\Services\PaystackClient;
use App\Services\Service;

/**
 * Confirms a Paystack card checkout after the gateway redirects back.
 * The amount and currency are re-checked against the attempt so a
 * tampered reference cannot unlock a plan it did not pay for. Safe to
 * run twice: an already-succeeded attempt is returned untouched.
 */
class ConfirmCardPayment extends Service
{
    use RenewsSubscriptions;

    public function __construct(private PaystackClient $paystack) {}

    public function handle(string $reference): PaymentAttempt
    {
        $attempt = PaymentAttempt::where('gateway', 'paystack')
            ->where('gateway_reference', $reference)
            ->first();

        if ($attempt === null) {
            throw new \DomainException('This payment could not be verified. Please try again.');
        }

        if ($attempt->isSucceeded()) {
            return $attempt;
        }

        $response = $this->paystack->verify($reference);

        $paid = ($response->status ?? false) === true
            && (($response->data->status ?? null) === 'success')
            && (int) ($response->data->amount ?? -1) === PaystackClient::toLowestUnit((string) $attempt->amount)
            && (($response->data->currency ?? null) === $attempt->currency);

        if (! $paid) {
            $this->save($attempt->fill(['status' => 'failed']));

            throw new \DomainException('The card payment did not go through. No charge was applied to your plan.');
        }

        return $this->transaction(function () use ($attempt) {
            $organization = $attempt->organization;

            app(CreateSubscriptionPayment::class)->handle([
                'organization_id' => $organization->id,
                'plan_id' => $attempt->plan_id,
                'amount' => $attempt->amount,
                'currency' => $attempt->currency,
                'received_on' => today()->toDateString(),
                'period_start' => today()->toDateString(),
                'period_end' => today()->copy()->addMonth()->toDateString(),
                'method' => 'card',
                'reference' => $attempt->gateway_reference,
            ], null);

            $this->renewSubscription($organization);

            return $this->save($attempt->fill(['status' => 'succeeded']));
        });
    }
}
