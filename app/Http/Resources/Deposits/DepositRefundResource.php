<?php

declare(strict_types=1);

namespace App\Http\Resources\Deposits;

use App\Models\DepositRefund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DepositRefund
 */
class DepositRefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lease_id' => $this->lease_id,
            'amount_cents' => $this->amount_cents,
            'amount' => $this->amount(),
            'currency' => $this->currency,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_variant' => $this->status->badgeVariant(),
            'requested_by' => $this->requested_by,
            'requester_name' => $this->whenLoaded('requester', fn () => $this->requester?->name),
            'decided_by' => $this->decided_by,
            'decider_name' => $this->whenLoaded('decider', fn () => $this->decider?->name),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'decision_note' => $this->decision_note,
            'tenant_name' => $this->whenLoaded('lease', fn () => $this->lease?->occupant?->person?->full_name ?: null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
