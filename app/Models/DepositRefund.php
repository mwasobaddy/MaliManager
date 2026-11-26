<?php

namespace App\Models;

use App\Enums\DepositRefundStatus;
use App\Models\Concerns\LogsTenantActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    use HasFactory, LogsTenantActivity, SoftDeletes;

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
        'paid_by',
        'paid_at',
        'paid_reference',
        'reviewed_by',
        'reviewed_at',
        'review_note',
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
            'paid_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(DepositDeduction::class, 'refund_id');
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

    /**
     * Refunds with money still in play: pending, under inspection or
     * review, or approved but not yet paid. Rejected and paid refunds
     * are closed and never block a fresh request.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            DepositRefundStatus::Pending->value,
            DepositRefundStatus::AwaitingInspection->value,
            DepositRefundStatus::InReview->value,
            DepositRefundStatus::Approved->value,
        ]);
    }

    /** Money out, formatted for display. */
    public function amount(): string
    {
        return number_format($this->amount_cents / 100, 2, '.', ',');
    }
}
