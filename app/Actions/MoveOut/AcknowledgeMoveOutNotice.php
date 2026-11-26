<?php

declare(strict_types=1);

namespace App\Actions\MoveOut;

use App\Actions\Deposits\AutoRequestDepositRefund;
use App\Models\MoveOutNotice;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Service;
use Carbon\CarbonImmutable;

/**
 * Acknowledges a tenant's move-out notice and starts what comes next.
 *
 * Acknowledging is the landlord's handshake: optionally confirm a
 * different move-out day (the schedule both sides work to), auto-file
 * the deposit refund when there is one to inspect, and tell the tenant
 * what happens next — all in one transaction so partial state is
 * impossible.
 */
class AcknowledgeMoveOutNotice extends Service
{
    public function __construct(
        private DecideMoveOutNotice $decide,
        private AutoRequestDepositRefund $autoRequest,
        private NotificationService $notifications,
    ) {}

    public function handle(User $actor, Organization $organization, MoveOutNotice $notice, ?string $moveOutDate = null): MoveOutNotice
    {
        abort_unless($notice->organization_id === $organization->id, 404);

        return $this->transaction(function () use ($actor, $organization, $notice, $moveOutDate): MoveOutNotice {
            $notice = $this->decide->acknowledge($actor, $notice);

            $scheduled = $moveOutDate !== null && trim($moveOutDate) !== ''
                ? CarbonImmutable::parse(trim($moveOutDate))->toDateString()
                : null;

            if ($scheduled !== null) {
                abort_if($scheduled < now()->toDateString(), 422, 'The scheduled move-out day cannot be in the past.');
                $notice->forceFill(['move_out_date' => $scheduled]);
                $this->save($notice);
            }

            $refund = $this->autoRequest->handle($organization, $notice->lease, $actor, $notice, 'notice');

            $tenant = $notice->requested_by !== null
                ? User::query()->find($notice->requested_by)
                : null;

            if ($tenant !== null) {
                $unit = $notice->lease?->unit?->name ?? 'your unit';

                $this->notifications->sendOperationalNotice(
                    $tenant,
                    "Move-out acknowledged for {$unit}",
                    "Your landlord acknowledged your move-out notice for {$unit}."
                    .($refund !== null ? " A deposit refund of {$refund->currency} {$refund->amount()} has been filed and is now awaiting inspection." : '')
                    .($scheduled !== null ? " Confirmed move-out day: {$scheduled}." : ''),
                );
            }

            return $notice->refresh();
        });
    }
}
