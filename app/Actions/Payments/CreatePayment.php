<?php

namespace App\Actions\Payments;

use App\Actions\Payments\Concerns\PaymentHelpers;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\Service;

class CreatePayment extends Service
{
    use PaymentHelpers;

    public function handle(User $actor, int $organizationId, array $data): Payment
    {
        return $this->transaction(function () use ($actor, $organizationId, $data) {
            $lease = app(PaymentService::class)->resolveLease($data + ['organization_id' => $organizationId]);

            $payment = new Payment([
                'tenant_id' => $this->tenantId(),
                'organization_id' => $organizationId,
                'lease_id' => $lease->id,
                'occupant_id' => $lease->occupant_id,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'KES',
                'paid_on' => $data['paid_on'],
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'status' => $data['status'] ?? 'received',
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            return $this->save($payment);
        });
    }
}
