<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An organization-level rent payment recorded against a lease. A payment
 * becomes the source for a rent receipt: amount, period, method, reference
 * and the lease's occupant/property are combined when the receipt is printed.
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    public const METHODS = [
        'cash',
        'mpesa',
        'bank',
        'cheque',
        'card',
        'other',
    ];

    /** @var list<string> */
    public const STATUSES = [
        'received',
        'pending',
        'refunded',
    ];

    protected $fillable = [
        'tenant_id',
        'organization_id',
        'lease_id',
        'occupant_id',
        'amount',
        'currency',
        'paid_on',
        'period_start',
        'period_end',
        'method',
        'reference',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function occupant(): BelongsTo
    {
        return $this->belongsTo(Occupant::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForOrganization(Builder $query, ?int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
