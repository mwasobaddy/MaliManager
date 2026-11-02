<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Data\Deposits\RequestDepositRefundData;
use App\Enums\DepositRefundStatus;
use App\Models\DepositRefund;
use App\Models\User;

/**
 * Opens a deposit-refund request in `Pending`.
 *
 * The amount is checked against the lease deposit in the Form Request, because
 * that is a fact about the submitted input and belongs next to the other rules.
 * This action owns only the write and the initial state.
 */
class RequestDepositRefund
{
    public function handle(User $requester, RequestDepositRefundData $data): DepositRefund
    {
        return DepositRefund::create([
            'organization_id' => $data->organizationId,
            'lease_id' => $data->leaseId,
            'amount_cents' => $data->amountCents,
            'currency' => $data->currency,
            'reason' => $data->reason,
            'status' => DepositRefundStatus::Pending,
            'requested_by' => $requester->id,
        ]);
    }
}
