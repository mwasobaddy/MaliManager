<?php

namespace App\Models;

use App\Enums\UtilityType;
use App\Support\SearchTerms;
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
class UtilityReading extends Model
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
     * Sort keys the index accepts. `unit` orders through a subselect so the
     * listing never needs a join that would collide on `id` under
     * pagination. Anything else falls back to newest-first.
     */
    public const SORTABLE = [
        'unit' => 'unit',
        'utility' => 'utility_readings.utility',
        'reading' => 'utility_readings.reading',
        'read_on' => 'utility_readings.read_on',
    ];

    /**
     * The list query: organization scope plus the index's search, unit,
     * utility and sort filters.
     *
     * Lives here rather than in the controller so the same predicate can be
     * reused by the export without a second copy drifting.
     *
     * @param  array{organization_id: int, unit_id: int|null, utility: string|null, search?: string, sort?: string, direction?: string}  $filters
     */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        $sortKey = self::SORTABLE[$filters['sort'] ?? ''] ?? null;
        $direction = ($filters['direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        return $query
            ->forOrganization($filters['organization_id'])
            ->when($filters['unit_id'], fn (Builder $q) => $q->where('unit_id', $filters['unit_id']))
            ->when($filters['utility'], fn (Builder $q) => $q->where('utility', $filters['utility']))
            ->when(
                ($terms = SearchTerms::split($filters['search'] ?? '')) !== [],
                fn (Builder $builder) => $builder->where(
                    function (Builder $nested) use ($terms): void {
                        foreach ($terms as $term) {
                            $nested->whereHas('unit', fn (Builder $unit) => $unit
                                ->where('units.name', 'like', "%{$term}%"));
                        }
                    }
                )
            )
            ->when(
                $sortKey === 'unit',
                fn (Builder $builder) => $builder
                    ->orderBy(Unit::select('name')->whereColumn('units.id', 'utility_readings.unit_id'), $direction),
            )
            ->when(
                $sortKey !== null && $sortKey !== 'unit',
                fn (Builder $builder) => $builder->orderBy($sortKey, $direction),
            )
            ->when($sortKey === null, fn (Builder $builder) => $builder->orderByDesc('utility_readings.read_on'))
            ->orderByDesc('utility_readings.id');
    }
}
