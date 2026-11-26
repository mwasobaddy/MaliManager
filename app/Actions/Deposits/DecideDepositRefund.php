<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Data\Deposits\DecideDepositRefundData;
use App\Enums\DepositRefundStatus;
use App\Models\DepositRefund;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Service;

/**
 * Approves or rejects a pending deposit refund.
 *
 * The state guard lives here rather than in the controller: only `Pending`
 * may move, so in-review refunds must pass review first and paid or
 * rejected ones can never be re-decided. `UpdateDepositRefundRequest`
 * surfaces the same rule to the user as a field error; this is the
 * enforcement. The tenant is notified either way.
 */
class DecideDepositRefund extends Service
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(DepositRefund $refund, User $decider, DecideDepositRefundData $data): DepositRefund
    {
        // Idempotent for closed refunds: a retry or double-click after a
        // decision lands back on the same record, not a second payout.
        // Inspection states still abort — they must pass review first.
        if ($refund->status->isDecided()) {
            return $refund;
        }

        abort_unless($refund->status === DepositRefundStatus::Pending, 422, 'Only a pending refund can be decided.');

        return $this->transaction(function () use ($refund, $decider, $data): DepositRefund {
            $refund->forceFill([
                'status' => $data->approve
                    ? DepositRefundStatus::Approved
                    : DepositRefundStatus::Rejected,
                'decided_by' => $decider->id,
                'decided_at' => now(),
                'decision_note' => $data->note,
            ]);
            $this->save($refund);

            $verdict = $data->approve ? 'approved' : 'rejected';

            $this->notifications->notifyTenantForRefund(
                $refund,
                "Deposit refund {$verdict}",
                "Your deposit refund of {$refund->currency} {$refund->amount()} has been {$verdict}."
                .($data->note !== null && $data->note !== '' ? " Note from your landlord: {$data->note}" : ''),
            );

            return $refund->refresh();
        });
    }
}
