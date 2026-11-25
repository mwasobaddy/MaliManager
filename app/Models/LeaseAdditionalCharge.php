<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Free-form extras on one lease: parking, pets, tenant-specific fees the
 * property template cannot express. Unlike `UtilityCharge` (a reusable
 * property template assigned to units), these live and die with the lease.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $lease_id
 * @property string $name
 * @property string $kind
 * @property string $amount
 * @property string $currency
 * @property string|null $gl_code
 * @property bool $is_active
 * @property Carbon|null $consumed_at
 */
class LeaseAdditionalCharge extends Model
{
    protected $fillable = [
        'organization_id',
        'lease_id',
        'name',
        'kind',
        'amount',
        'currency',
        'gl_code',
        'is_active',
        'consumed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
            'consumed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function label(): string
    {
        return match ($this->kind) {
            'one_time' => 'One-time',
            default => 'Every period',
        };
    }
}
