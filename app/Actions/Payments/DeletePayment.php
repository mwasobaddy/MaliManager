<?php

namespace App\Actions\Payments;

use App\Actions\Payments\Concerns\PaymentHelpers;
use App\Models\Payment;
use App\Services\Service;

class DeletePayment extends Service
{
    use PaymentHelpers;

    public function handle(Payment $payment): void
    {
        $this->transaction(function () use ($payment) {
            $this->delete($payment);
        });
    }
}
