<?php

declare(strict_types=1);

namespace App\Actions\MoveOut;

use App\Enums\MoveOutNoticeStatus;
use App\Models\MoveOutNotice;
use App\Models\User;
use App\Services\Service;

/**
 * Acknowledges a pending notice (landlord side) or withdraws it (tenant
 * side). Only pending notices move; acknowledged and withdrawn are terminal.
 */
class DecideMoveOutNotice extends Service
{
    public function acknowledge(User $actor, MoveOutNotice $notice): MoveOutNotice
    {
        abort_unless($notice->status === MoveOutNoticeStatus::Pending, 422, 'Only a pending notice can be acknowledged.');

        return $this->transaction(function () use ($notice): MoveOutNotice {
            $notice->forceFill(['status' => MoveOutNoticeStatus::Acknowledged]);
            $this->save($notice);

            return $notice->refresh();
        });
    }

    public function withdraw(User $actor, MoveOutNotice $notice): MoveOutNotice
    {
        abort_unless($notice->status === MoveOutNoticeStatus::Pending, 422, 'Only a pending notice can be withdrawn.');
        abort_unless($notice->requested_by === $actor->id, 403, 'Only the tenant who gave notice can withdraw it.');

        return $this->transaction(function () use ($notice): MoveOutNotice {
            $notice->forceFill(['status' => MoveOutNoticeStatus::Withdrawn]);
            $this->save($notice);

            return $notice->refresh();
        });
    }
}
