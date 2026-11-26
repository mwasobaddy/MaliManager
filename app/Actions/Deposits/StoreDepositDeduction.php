<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Enums\DepositRefundStatus;
use App\Models\DepositDeduction;
use App\Models\DepositRefund;
use App\Models\User;
use App\Services\Service;
use Illuminate\Http\UploadedFile;

/**
 * Adds a damage line item with photo proof to an awaiting-inspection
 * refund. Photos are mandatory: an undocumented deduction is a dispute
 * waiting to happen.
 */
class StoreDepositDeduction extends Service
{
    /**
     * @param  array{description: string, amount: string|float|int}  $data
     * @param  list<UploadedFile>  $photos
     */
    public function handle(DepositRefund $refund, User $actor, array $data, array $photos): DepositDeduction
    {
        abort_unless($refund->status === DepositRefundStatus::AwaitingInspection, 422, 'Deductions can only be added while a refund awaits inspection.');
        abort_if($photos === [], 422, 'Attach at least one photo as proof.');

        return $this->transaction(function () use ($actor, $refund, $data, $photos): DepositDeduction {
            $deduction = new DepositDeduction([
                'organization_id' => $refund->organization_id,
                'refund_id' => $refund->id,
                'description' => $data['description'],
                'amount_cents' => (int) round((float) $data['amount'] * 100),
                'created_by' => $actor->id,
            ]);
            $this->save($deduction);

            foreach (array_slice($photos, 0, DepositDeduction::MAX_PHOTOS) as $photo) {
                $deduction->addMedia($photo)->toMediaCollection('photos');
            }

            return $deduction->refresh();
        });
    }
}
