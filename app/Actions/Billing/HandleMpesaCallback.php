<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\RenewsSubscriptions;
use App\Actions\SubscriptionPayments\CreateSubscriptionPayment;
use App\Models\PaymentAttempt;
use App\Services\Service;
use Botnetdobbs\Mpesa\Contracts\CallbackProcessor;
use Illuminate\Http\Request;

/**
 * Applies an M-Pesa STK callback to its attempt. Safe to receive twice:
 * an already-succeeded attempt is acknowledged without recording again.
 * The controller always answers Safaricom's expected envelope; failures
 * are recorded on the attempt, never bounced back for retry.
 */
class HandleMpesaCallback extends Service
{
    use RenewsSubscriptions;

    public function __construct(private CallbackProcessor $callbacks) {}

    public function handle(Request $request): ?PaymentAttempt
    {
        $result = $this->callbacks->handle($request);
        $callback = $result->getData()->Body->stkCallback ?? null;

        if ($callback === null || ! isset($callback->CheckoutRequestID)) {
            logger()->warning('Billing received an unrecognized M-Pesa callback.');

            return null;
        }

        $attempt = PaymentAttempt::where('gateway', 'mpesa')
            ->where('gateway_reference', $callback->CheckoutRequestID)
            ->first();

        if ($attempt === null) {
            logger()->warning('Billing received an M-Pesa callback for an unknown checkout request.', [
                'checkout_request_id' => $callback->CheckoutRequestID,
            ]);

            return null;
        }

        if ($attempt->isSucceeded()) {
            return $attempt;
        }

        if (! $result->isSuccessful()) {
            $this->save($attempt->fill([
                'status' => 'failed',
                'meta' => [...($attempt->meta ?? []), 'result_description' => $callback->ResultDesc ?? 'Failed'],
            ]));

            return $attempt;
        }

        return $this->transaction(function () use ($attempt, $callback) {
            $receipt = $this->receiptNumber($callback);

            $organization = $attempt->organization;

            app(CreateSubscriptionPayment::class)->handle([
                'organization_id' => $organization->id,
                'plan_id' => $attempt->plan_id,
                'amount' => $attempt->amount,
                'currency' => $attempt->currency,
                'received_on' => today()->toDateString(),
                'period_start' => today()->toDateString(),
                'period_end' => today()->copy()->addMonth()->toDateString(),
                'method' => 'mpesa',
                'reference' => $receipt,
            ], null);

            $this->renewSubscription($organization);

            return $this->save($attempt->fill([
                'status' => 'succeeded',
                'meta' => [...($attempt->meta ?? []), 'receipt' => $receipt],
            ]));
        });
    }

    private function receiptNumber(object $callback): ?string
    {
        $items = $callback->CallbackMetadata->Item ?? [];

        foreach (is_array($items) ? $items : [] as $item) {
            if (($item->Name ?? null) === 'MpesaReceiptNumber') {
                return (string) $item->Value;
            }
        }

        return null;
    }
}
