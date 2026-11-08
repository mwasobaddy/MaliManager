<?php

namespace App\Actions\Billing;

use App\Models\Organization;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PaystackClient;
use App\Services\Service;
use Illuminate\Support\Str;

/**
 * Starts a Paystack hosted card checkout (Visa, Mastercard and the other
 * card channels Paystack supports) for the organization's plan. Returns
 * the authorization URL the user is redirected to; Paystack calls back
 * to the card callback URL on completion.
 */
class InitializeCardPayment extends Service
{
    public function __construct(
        private PaystackClient $paystack,
        private BillingService $billing,
    ) {}

    public function handle(Organization $organization, User $actor): string
    {
        $plan = $organization->plan;

        abort_unless($plan !== null && $plan->price > 0, 422, 'There is nothing to pay for on this plan.');

        return $this->transaction(function () use ($organization, $actor, $plan) {
            $attempt = $this->save(new PaymentAttempt([
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'amount' => $plan->price,
                'currency' => $plan->currency ?? 'KES',
                'gateway' => 'paystack',
                'status' => 'pending',
                'gateway_reference' => 'MM-'.Str::upper(Str::random(12)),
            ]));

            $response = $this->paystack->initialize([
                'email' => $actor->email,
                'amount' => PaystackClient::toLowestUnit((string) $attempt->amount),
                'currency' => $attempt->currency,
                'reference' => $attempt->gateway_reference,
                'callback_url' => $this->billing->callbackUrl($organization, 'tenant.billing.card.callback'),
                'channels' => ['card'],
                'metadata' => [
                    'organization_id' => $organization->id,
                    'plan_id' => $plan->id,
                    'attempt_id' => $attempt->id,
                ],
            ]);

            if (! ($response->status ?? false) || ! isset($response->data->authorization_url)) {
                $this->save($attempt->fill(['status' => 'failed']));

                throw new \DomainException('The card checkout could not be started. Please try again.');
            }

            $this->save($attempt->fill([
                'meta' => ['access_code' => $response->data->access_code ?? null],
            ]));

            return $response->data->authorization_url;
        });
    }
}
