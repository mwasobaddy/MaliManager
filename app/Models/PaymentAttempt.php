<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A self-serve subscription payment attempt (M-Pesa STK push or Paystack
 * card checkout). Only completed income belongs in SubscriptionPayment;
 * attempts track the async gateway round-trip (pending → succeeded/failed)
 * so callbacks can correlate back to the organization and plan.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $plan_id
 * @property string $amount
 * @property string $currency
 * @property string $gateway
 * @property string $status
 * @property string|null $gateway_reference
 * @property array|null $meta
 */
class PaymentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'plan_id',
        'amount',
        'currency',
        'gateway',
        'status',
        'gateway_reference',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSucceeded(): bool
    {
        return $this->status === 'succeeded';
    }
}
