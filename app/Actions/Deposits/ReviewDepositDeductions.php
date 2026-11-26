<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Data\Deposits\ReviewDepositDeductionsData;
use App\Enums\DepositRefundStatus;
use App\Models\DepositRefund;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Service;

/**
 * Reviews the inspected deductions. Approving moves the refund to pending,
 * ready for the final payout decision; rejecting sends it back to
 * awaiting inspection with the reason recorded, never silently.
 */
class ReviewDepositDeductions extends Service
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(DepositRefund $refund, User $actor, ReviewDepositDeductionsData $data): DepositRefund
    {
        abort_unless($refund->status === DepositRefundStatus::InReview, 422, 'Only a submitted refund can be reviewed.');

        return $this->transaction(function () use ($actor, $refund, $data): DepositRefund {
            $refund->forceFill([
                'status' => $data->approve ? DepositRefundStatus::Pending : DepositRefundStatus::AwaitingInspection,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $data->note,
            ]);
            $this->save($refund);

            if ($data->approve) {
                $this->notifications->notifyTenantForRefund(
                    $refund,
                    "Deposit deductions accepted ({$refund->currency} {$refund->amount()})",
                    'The deductions against your deposit have been reviewed and accepted. The refund now awaits final approval.',
                );
            } else {
                $organization = Organization::find($refund->organization_id);

                if ($organization !== null) {
                    $this->notifications->notifyOwners(
                        $organization,
                        'Deposit deductions sent back for re-inspection',
                        "The deductions for the {$refund->currency} {$refund->amount()} refund were sent back."
                        .($data->note !== null && $data->note !== '' ? " Note: {$data->note}" : ''),
                    );
                }
            }

            return $refund->refresh();
        });
    }
}
