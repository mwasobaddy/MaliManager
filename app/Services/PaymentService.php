<?php

namespace App\Services;

use App\Actions\Payments\Concerns\PaymentHelpers;
use App\Actions\Payments\CreatePayment;
use App\Actions\Payments\DeletePayment;
use App\Actions\Payments\UpdatePayment;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;

/**
 * Records an organization-level rent payment against a lease. The lease's
 * occupant (if any) is denormalized onto the payment so a receipt can be
 * rendered after the lease is ended.
 */
class PaymentService extends Service
{
    use PaymentHelpers;

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
        return app(CreatePayment::class)->handle($actor, $organizationId, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Payment $payment, array $data): Payment
    {
        return app(UpdatePayment::class)->handle($payment, $data);
    }

    public function softDelete(Payment $payment): void
    {
        app(DeletePayment::class)->handle($payment);
    }
}
