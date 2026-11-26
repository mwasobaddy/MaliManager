<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Enums\DepositRefundStatus;
use App\Models\DepositDeduction;
use App\Models\DepositRefund;
use App\Models\User;
use App\Services\Service;

/**
 * Removes a damage line item. Only possible while the refund still awaits
 * inspection — once submitted, the list is locked for review.
 */
class DeleteDepositDeduction extends Service
{
    public function handle(DepositRefund $refund, DepositDeduction $deduction, User $actor): void
    {
        abort_unless($deduction->refund_id === $refund->id, 404);
        abort_unless($refund->status === DepositRefundStatus::AwaitingInspection, 422, 'Submitted deductions can no longer be removed.');

        $this->transaction(function () use ($deduction): void {
            $deduction->clearMediaCollection('photos');
            $this->delete($deduction);
        });
    }
}
