<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Data\Deposits\DecideDepositRefundData;
use App\Enums\DepositRefundStatus;
use App\Models\DepositRefund;
use App\Models\User;

/**
 * Approves or rejects a pending deposit refund.
 *
 * The state guard lives here rather than in the controller: only `Pending` may
 * move, so a paid or rejected refund cannot be re-decided. `UpdateDepositRefundRequest`
 * surfaces the same rule to the user as a field error; this is the enforcement.
 */
class DecideDepositRefund
{
    public function handle(DepositRefund $refund, User $decider, DecideDepositRefundData $data): DepositRefund
    {
        if ($refund->status->isDecided()) {
            return $refund;
        }

        $refund->forceFill([
            'status' => $data->approve
                ? DepositRefundStatus::Approved
                : DepositRefundStatus::Rejected,
            'decided_by' => $decider->id,
            'decided_at' => now(),
            'decision_note' => $data->note,
        ])->save();

        return $refund;
    }
}
