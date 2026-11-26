<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Enums\DepositRefundStatus;
use App\Models\DepositRefund;
use App\Models\Lease;
use App\Models\MoveOutNotice;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Service;

/**
 * Files a refund when a tenancy ends or its move-out notice is
 * acknowledged. Idempotent by construction: an open refund (anything but
 * rejected or paid) stops a second one, and a lease with no deposit held
 * has nothing to return. The refund starts at awaiting_inspection — the
 * inspection happens next, not at filing time.
 */
class AutoRequestDepositRefund extends Service
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(Organization $organization, Lease $lease, User $actor, ?MoveOutNotice $notice, string $trigger): ?DepositRefund
    {
        abort_unless($lease->organization_id === $organization->id, 404);

        if ($notice !== null) {
            abort_unless($notice->organization_id === $organization->id, 404);
            abort_unless($notice->lease_id === $lease->id, 422, 'That notice belongs to a different lease.');
        }

        if ((float) ($lease->deposit ?? 0) <= 0) {
            return null;
        }

        $open = DepositRefund::query()
            ->where('lease_id', $lease->id)
            ->whereIn('status', [
                DepositRefundStatus::Pending->value,
                DepositRefundStatus::AwaitingInspection->value,
                DepositRefundStatus::InReview->value,
                DepositRefundStatus::Approved->value,
            ])
            ->exists();

        if ($open) {
            return null;
        }

        return $this->transaction(function () use ($organization, $lease, $trigger): DepositRefund {
            $refund = $this->save(new DepositRefund([
                'organization_id' => $organization->id,
                'lease_id' => $lease->id,
                'amount_cents' => (int) round((float) $lease->deposit * 100),
                'currency' => $lease->currency ?? 'KES',
                'reason' => $trigger === 'notice'
                    ? 'Auto-requested on move-out notice acknowledgement — awaiting inspection.'
                    : 'Auto-requested when the lease ended — awaiting inspection.',
                'status' => DepositRefundStatus::AwaitingInspection,
            ]));

            $this->notifications->notifyTenantForRefund(
                $refund->refresh(),
                "Deposit refund filed for {$lease->unit?->name}",
                "A deposit refund of {$refund->currency} {$refund->amount()} has been filed for your tenancy and is awaiting inspection.",
            );

            return $refund;
        });
    }
}
