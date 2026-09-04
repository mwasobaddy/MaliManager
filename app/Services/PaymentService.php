<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use App\Support\TenancyContext;

/**
 * Records an organization-level rent payment against a lease. The lease's
 * occupant (if any) is denormalized onto the payment so a receipt can be
 * rendered after the lease is ended.
 */
class PaymentService extends Service
{
    /**
     * Validate that the chosen lease belongs to the organization.
     *
     * @param  array<string, mixed>  $data
     */
    public function resolveLease(array $data): Lease
    {
        /** @var Lease|null $lease */
        $lease = Lease::query()->find((int) ($data['lease_id'] ?? 0));

        if (! $lease || $lease->organization_id !== ($data['organization_id'] ?? null)) {
            abort(422, 'The selected lease does not belong to this organization.');
        }

        return $lease;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, int $organizationId, array $data): Payment
    {
        return $this->transaction(function () use ($actor, $organizationId, $data) {
            $lease = $this->resolveLease($data + ['organization_id' => $organizationId]);

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

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Payment $payment, array $data): Payment
    {
        return $this->transaction(function () use ($payment, $data) {
            $lease = $this->resolveLease($data + ['organization_id' => $payment->organization_id]);

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

    public function softDelete(Payment $payment): void
    {
        $this->transaction(function () use ($payment) {
            $this->delete($payment);
        });
    }

    private function tenantId(): ?string
    {
        return TenancyContext::tenantId();
    }
}
