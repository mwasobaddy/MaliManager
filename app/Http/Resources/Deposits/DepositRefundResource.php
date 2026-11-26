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
            'reviewed_by' => $this->reviewed_by,
            'reviewer_name' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_note' => $this->review_note,
            'deductions' => $this->whenLoaded('deductions', fn () => $this->deductions->map(fn ($deduction): array => [
                'id' => $deduction->id,
                'description' => $deduction->description,
                'amount' => $deduction->amount(),
                'amount_cents' => $deduction->amount_cents,
                'photos' => $deduction->getMedia('photos')->map(fn ($media): array => [
                    'id' => $media->id,
                    'url' => $media->getUrl(),
                ])->all(),
            ])->all(), []),
            'tenant_name' => $this->whenLoaded('lease', fn () => $this->lease?->occupant?->person?->full_name ?: null),
            'unit_name' => $this->whenLoaded('lease', fn () => $this->lease?->unit?->name),
            'property_name' => $this->whenLoaded('lease', fn () => $this->lease?->property?->name),
            'paid_by' => $this->paid_by,
            'payer_name' => $this->whenLoaded('payer', fn () => $this->payer?->name),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'paid_reference' => $this->paid_reference,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
