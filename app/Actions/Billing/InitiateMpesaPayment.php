<?php

namespace App\Actions\Billing;

use App\Models\Organization;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\BillingService;
use App\Services\Service;
use Botnetdobbs\Mpesa\Contracts\Client;

/**
 * Sends the M-Pesa STK push that asks the owner's phone for the plan
 * price. Creates the pending attempt first so the async callback can
 * correlate back via the checkout request id.
 */
class InitiateMpesaPayment extends Service
{
    public function __construct(
        private Client $mpesa,
        private BillingService $billing,
    ) {}

    public function handle(Organization $organization, User $actor, string $phone): PaymentAttempt
    {
        $plan = $organization->plan;

        abort_unless($plan !== null && $plan->price > 0, 422, 'There is nothing to pay for on this plan.');

        $shortcode = (string) config('services.mpesa.shortcode');

        if ($shortcode === '') {
            throw new \DomainException('Mobile payments are not configured yet. Please try again later.');
        }

        return $this->transaction(function () use ($organization, $phone, $plan, $shortcode) {
            $attempt = $this->save(new PaymentAttempt([
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'amount' => $plan->price,
                'currency' => $plan->currency ?? 'KES',
                'gateway' => 'mpesa',
                'status' => 'pending',
            ]));

            $response = $this->mpesa->stkPush([
                'BusinessShortCode' => $shortcode,
                'Amount' => (int) $plan->price,
                'PhoneNumber' => self::normalizePhone($phone),
                'CallBackURL' => $this->billing->callbackUrl($organization, 'tenant.billing.mpesa.callback'),
                'AccountReference' => 'MM'.$attempt->id,
                'TransactionDesc' => 'Subscription',
            ]);

            if (! $response->isSuccessful()) {
                $this->save($attempt->fill(['status' => 'failed']));

                throw new \DomainException(
                    $response->getResponseDescription() !== ''
                        ? $response->getResponseDescription()
                        : 'The payment request was not accepted. Please try again.',
                );
            }

            return $this->save($attempt->fill([
                'gateway_reference' => $response->getData()->CheckoutRequestID,
            ]));
        });
    }

    /**
     * Daraja wants 2547XXXXXXXX: accept 07…/01… and +254… spellings.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/^\+/', '', trim($phone));

        if (str_starts_with($digits, '0')) {
            return '254'.substr($digits, 1);
        }

        return $digits;
    }
}
