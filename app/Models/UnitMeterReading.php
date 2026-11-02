<?php

namespace App\Models;

use App\Enums\UtilityType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A utility meter reading for one unit on one day.
 *
 * `consumption` is stored rather than derived because it is a billing fact:
 * recalculating it later from "the previous reading" would silently change a
 * number that has already appeared on an invoice. The Action computes it once,
 * at record time, from the reading before it.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $unit_id
 * @property UtilityType $utility
 * @property string $reading
 * @property string|null $consumption
 * @property Carbon $read_on
 * @property string|null $notes
 * @property int|null $recorded_by
 */
class UnitMeterReading extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'unit_id',
        'utility',
        'reading',
        'consumption',
        'read_on',
        'notes',
        'recorded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'utility' => UtilityType::class,
            'reading' => 'decimal:3',
            'consumption' => 'decimal:3',
            'read_on' => 'date',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForUtility(Builder $query, UtilityType $utility): Builder
    {
        return $query->where('utility', $utility);
    }

    /**
     * The list query: organization scope plus the two optional filters.
     *
     * Lives here rather than in the controller so the same predicate can be
     * reused by any future export or report without a second copy drifting.
     *
     * @param  array{organization_id: int, unit_id: int|null, utility: string|null}  $filters
     */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        return $query
            ->forOrganization($filters['organization_id'])
            ->when($filters['unit_id'], fn (Builder $q) => $q->where('unit_id', $filters['unit_id']))
            ->when($filters['utility'], fn (Builder $q) => $q->where('utility', $filters['utility']));
    }
}
