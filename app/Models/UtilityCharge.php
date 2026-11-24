<?php

namespace App\Models;

use App\Enums\UtilityChargeKind;
use App\Enums\UtilityType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * What the landlord charges for a utility at a property: a metered rate,
 * a fixed per-unit amount, or a one-time fee. Landlord-scoped, not global —
 * tariffs differ per building.
 *
 * Which units pay is explicit (`units` pivot): units without a row absorb
 * the cost or aren't metered. Per-unit amounts live on the pivot so garbage
 * can cost 500 on A1 and 800 on A2 under one charge.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property string $name
 * @property UtilityChargeKind $kind
 * @property UtilityType|null $utility
 * @property string|null $rate
 * @property string $currency
 * @property bool $is_active
 */
class UtilityCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'property_id',
        'name',
        'kind',
        'utility',
        'rate',
        'currency',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => UtilityChargeKind::class,
            'utility' => UtilityType::class,
            'rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'utility_charge_unit')
            ->withPivot(['amount', 'last_billed_reading_id', 'consumed_at'])
            ->withTimestamps();
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
