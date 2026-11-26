<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Enums\DepositRefundStatus;
use App\Models\DepositRefund;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Service;

/**
 * Sends an inspected refund for review. The inspection stays open until
 * someone explicitly submits it, so a half-documented list never lands on
 * a reviewer's desk by accident.
 */
class SubmitDepositRefundForReview extends Service
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(DepositRefund $refund, User $actor): DepositRefund
    {
        abort_unless($refund->status === DepositRefundStatus::AwaitingInspection, 422, 'Only an inspected refund can be submitted for review.');

        return $this->transaction(function () use ($refund): DepositRefund {
            $refund->forceFill(['status' => DepositRefundStatus::InReview]);
            $this->save($refund);

            $organization = Organization::find($refund->organization_id);

            if ($organization !== null) {
                $deductions = $refund->deductions()->count();
                $this->notifications->notifyOwners(
                    $organization,
                    "Deposit refund ready for review ({$refund->lease?->unit?->name})",
                    "Deductions submitted for the {$refund->currency} {$refund->amount()} refund ({$deductions} line item".($deductions === 1 ? '' : 's').'). Please review them.',
                );
            }

            return $refund->refresh();
        });
    }
}
