<?php

namespace App\Models;

use App\Enums\DepositRefundStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A request to return some or all of a lease's deposit.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $lease_id
 * @property int $amount_cents
 * @property string $currency
 * @property string $reason
 * @property DepositRefundStatus $status
 */
class DepositRefund extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'lease_id',
        'amount_cents',
        'currency',
        'reason',
        'status',
        'requested_by',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DepositRefundStatus::class,
            'amount_cents' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DepositRefundStatus::Pending->value);
    }

    /** Money out, formatted for display. */
    public function amount(): string
    {
        return number_format($this->amount_cents / 100, 2, '.', ',');
    }
}
