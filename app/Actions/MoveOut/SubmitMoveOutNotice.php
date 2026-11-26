<?php

declare(strict_types=1);

namespace App\Actions\MoveOut;

use App\Data\MoveOut\SubmitMoveOutNoticeData;
use App\Enums\MoveOutNoticeStatus;
use App\Models\Lease;
use App\Models\MoveOutNotice;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Service;

/**
 * Files a tenant's 30-day move-out notice. One open notice per lease:
 * a second one while the first is still pending would fork the
 * move-out date the landlord plans around.
 */
class SubmitMoveOutNotice extends Service
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(User $actor, Lease $lease, SubmitMoveOutNoticeData $data): MoveOutNotice
    {
        abort_unless($lease->organization_id === $data->organizationId, 404);
        abort_unless($lease->isActive(), 422, 'Only an active lease can receive a move-out notice.');

        abort_if(
            MoveOutNotice::query()
                ->where('lease_id', $lease->id)
                ->where('status', MoveOutNoticeStatus::Pending->value)
                ->exists(),
            422,
            'This lease already has a pending move-out notice.'
        );

        return $this->transaction(function () use ($actor, $lease, $data): MoveOutNotice {
            $notice = new MoveOutNotice([
                'organization_id' => $data->organizationId,
                'lease_id' => $lease->id,
                'requested_by' => $actor->id,
                'move_out_date' => $data->moveOutDate,
                'status' => MoveOutNoticeStatus::Pending,
                'note' => $data->note,
            ]);

            $this->save($notice);

            $organization = Organization::find($data->organizationId);

            if ($organization !== null) {
                $unit = $lease->unit?->name ?? "unit #{$lease->unit_id}";
                $property = $lease->property?->name ?? 'the property';

                $this->notifications->notifyOwners(
                    $organization,
                    "Move-out notice for {$unit}",
                    "{$actor->name} gave notice to vacate {$unit} ({$property}) on {$notice->move_out_date->toDateString()}.",
                );
            }

            return $notice->refresh();
        });
    }
}
