<?php

namespace App\Actions\Payments;

use App\Actions\Payments\Concerns\PaymentHelpers;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\Service;

class UpdatePayment extends Service
{
    use PaymentHelpers;

    public function handle(Payment $payment, array $data): Payment
    {
        return $this->transaction(function () use ($payment, $data) {
            $lease = app(PaymentService::class)->resolveLease($data + ['organization_id' => $payment->organization_id]);

            $payment->fill([
                'lease_id' => $lease->id,
                'occupant_id' => $lease->occupant_id,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? $payment->currency,
                'paid_on' => $data['paid_on'],
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'status' => $data['status'] ?? $payment->status,
                'notes' => $data['notes'] ?? null,
            ]);

            return $this->save($payment);
        });
    }
}
