<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Data\Deposits\MarkDepositRefundPaidData;
use App\Enums\DepositRefundStatus;
use App\Models\DepositRefund;
use App\Models\User;
use App\Services\Service;

/**
 * Marks an approved refund as paid. Only `approved` moves: paying a
 * pending refund would skip the decision, and re-paying a paid one
 * would double-count the payout.
 */
class MarkDepositRefundPaid extends Service
{
    public function handle(DepositRefund $refund, User $payer, MarkDepositRefundPaidData $data): DepositRefund
    {
        abort_unless($refund->status === DepositRefundStatus::Approved, 422, 'Only an approved refund can be marked paid.');

        return $this->transaction(function () use ($refund, $payer, $data): DepositRefund {
            $refund->forceFill([
                'status' => DepositRefundStatus::Paid,
                'paid_by' => $payer->id,
                'paid_at' => now(),
                'paid_reference' => $data->reference,
            ]);
            $this->save($refund);

            return $refund->refresh();
        });
    }
}
