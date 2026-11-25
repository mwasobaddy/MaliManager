<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Support\SearchTerms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A single rental episode for a global Person. Unlike Occupant (which is
 * scoped per organization), a Lease is person-centric and NOT tenant-scoped,
 * so a searcher can view every unit they have ever rented across orgs.
 *
 * @property int $id
 * @property int $person_id
 * @property int $organization_id
 * @property string|null $tenant_id
 * @property int $property_id
 * @property int $unit_id
 * @property int|null $occupant_id
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property float|null $rent_amount
 * @property string|null $rent_frequency
 * @property int|null $rent_due_day
 * @property int|null $rent_due_weekday
 * @property int|null $rent_due_month
 * @property \Carbon|null $overdue_since
 * @property string|null $currency
 * @property float|null $deposit
 * @property string|null $agreement_text
 * @property string $status
 * @property Carbon|null $last_reminded_at
 */
class Lease extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsTenantActivity;

    public function registerMediaCollections(): void
    {
        // A ready-made lease agreement uploaded by the owner. Single file:
        // a newly uploaded agreement replaces the previous one.
        $this->addMediaCollection('agreement')
            ->singleFile()
            ->acceptsMimeTypes([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);
    }

    protected $fillable = [
        'person_id',
        'organization_id',
        'tenant_id',
        'property_id',
        'unit_id',
        'occupant_id',
        'starts_at',
        'ends_at',
        'rent_amount',
        'rent_frequency',
        'rent_due_day',
        'rent_due_weekday',
        'rent_due_month',
        'overdue_since',
        'currency',
        'deposit',
        'agreement_text',
        'status',
        'last_reminded_at',
        'land_parcel_id',
        'land_parcel_section_id',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'rent_amount' => 'float',
        'deposit' => 'float',
        'rent_due_day' => 'integer',
        'rent_due_weekday' => 'integer',
        'rent_due_month' => 'integer',
        'overdue_since' => 'date',
        'last_reminded_at' => 'datetime',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function landParcel(): BelongsTo
    {
        return $this->belongsTo(LandParcel::class);
    }

    public function landParcelSection(): BelongsTo
    {
        return $this->belongsTo(LandParcelSection::class);
    }

    public function occupant(): BelongsTo
    {
        return $this->belongsTo(Occupant::class);
    }

    /**
     * Sort keys the index accepts. `unit` and `occupant` order through
     * subselects so the listing never needs a join that would collide on
     * `id` under pagination. Anything else falls back to newest-start
     * first, matching the list's original order.
     */
    public const SORTABLE = [
        'unit' => 'unit',
        'occupant' => 'occupant',
        'rent' => 'leases.rent_amount',
        'start' => 'leases.starts_at',
        'end' => 'leases.ends_at',
        'status' => 'leases.status',
    ];

    /**
     * The property-scoped lease listing: the index's search, status,
     * expiring and overdue filters, then the sort.
     *
     * Overdue is computed (expected vs received), so callers resolve the
     * ids first and pass them in; the scope only constrains to them.
     *
     * @param  array{search?: string, status?: string, expiring?: bool, overdue_ids?: int[]|null, sort?: string, direction?: string}  $filters
     */
    public function scopeTenantIndex(Builder $query, int $propertyId, array $filters): Builder
    {
        $sortKey = self::SORTABLE[$filters['sort'] ?? ''] ?? null;
        $direction = ($filters['direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        return $query
            ->where('leases.property_id', $propertyId)
            ->when(
                ($terms = SearchTerms::split($filters['search'] ?? '')) !== [],
                fn (Builder $builder) => $builder->where(
                    function (Builder $query) use ($terms): void {
                        foreach ($terms as $term) {
                            $query->where(
                                fn (Builder $inner) => $inner
                                    ->whereHas('person', fn (Builder $person) => $person->where(
                                        fn (Builder $name) => $name
                                            ->where('persons.first_name', 'like', "%{$term}%")
                                            ->orWhere('persons.last_name', 'like', "%{$term}%")
                                            ->orWhere('persons.email', 'like', "%{$term}%"),
                                    ))
                                    ->orWhereHas('unit', fn (Builder $unit) => $unit
                                        ->where('units.name', 'like', "%{$term}%")),
                            );
                        }
                    },
                ),
            )
            ->when(
                in_array($filters['status'] ?? '', ['active', 'ended', 'pending'], true),
                fn (Builder $builder) => $builder->where('leases.status', (string) $filters['status']),
            )
            // Expiring = active with an end date inside the next 60 days.
            ->when(
                ! empty($filters['expiring']),
                fn (Builder $builder) => $builder
                    ->where('leases.status', 'active')
                    ->whereNotNull('leases.ends_at')
                    ->whereBetween('leases.ends_at', [now()->toDateString(), now()->addDays(60)->toDateString()]),
            )
            ->when(
                is_array($filters['overdue_ids'] ?? null),
                fn (Builder $builder) => $builder->whereIn('leases.id', $filters['overdue_ids'] ?? []),
            )
            ->when(
                $sortKey === 'unit',
                fn (Builder $builder) => $builder
                    ->orderBy(Unit::select('name')->whereColumn('units.id', 'leases.unit_id'), $direction),
            )
            ->when(
                $sortKey === 'occupant',
                fn (Builder $builder) => $builder
                    ->orderBy(Person::select('first_name')->whereColumn('persons.id', 'leases.person_id'), $direction)
                    ->orderBy(Person::select('last_name')->whereColumn('persons.id', 'leases.person_id'), $direction),
            )
            ->when(
                $sortKey !== null && $sortKey !== 'unit' && $sortKey !== 'occupant',
                fn (Builder $builder) => $builder->orderBy($sortKey, $direction),
            )
            ->when($sortKey === null, fn (Builder $builder) => $builder->orderByDesc('leases.starts_at'))
            ->orderByDesc('leases.id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The rent history: inception terms plus every dated amendment, oldest
     * first. Arrears prices each period at the rate in effect on its due
     * date; the lease's own columns always mirror the latest row.
     */
    public function rentChanges(): HasMany
    {
        return $this->hasMany(LeaseRentChange::class)->orderBy('effective_date');
    }

    /**
     * Free-form extras on this lease (parking, pets). Property-template
     * charges live on `UtilityCharge`; these live and die with the lease.
     */
    public function additionalCharges(): HasMany
    {
        return $this->hasMany(LeaseAdditionalCharge::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Still running: status active and no end date, or an end date that
     * has not arrived. A lease expiring in 20 days owes rent and takes
     * reminders like any other; only a past end date retires it. Kept in
     * sync with scopeActive() below.
     */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->ends_at === null || ! $this->ends_at->startOfDay()->lessThan(now()->startOfDay()));
    }

    /**
     * Day of the month rent falls due: the landlord's explicit day, else
     * the starts_at anniversary. Only meaningful for monthly leases;
     * other frequencies use period boundaries in the arrears service.
     * Callers clamp to the month length (February has no 30th).
     */
    public function dueDay(): int
    {
        if ($this->rent_due_day !== null) {
            return min(31, max(1, $this->rent_due_day));
        }

        return $this->starts_at ? $this->starts_at->day : 1;
    }

    /**
     * Number of days the lease has run (or ran), from start to end (or now).
     */
    public function durationInDays(): ?int
    {
        if (! $this->starts_at) {
            return null;
        }

        return (int) $this->starts_at->diffInDays($this->ends_at ?? now());
    }

    /**
     * Total cost = rent across the rental period + deposit.
     * Periods are approximated from the rent frequency.
     */
    public function totalCost(): ?float
    {
        if ($this->rent_amount === null || ! $this->starts_at) {
            return null;
        }

        $days = $this->durationInDays() ?: 0;

        $periods = match ($this->rent_frequency) {
            'daily' => $days,
            'weekly' => $days / 7,
            'monthly' => $days / 30,
            'yearly' => $days / 365,
            default => 0,
        };

        return round($this->rent_amount * $periods, 2) + (float) ($this->deposit ?? 0);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where(function (Builder $inner): void {
            $inner->whereNull('ends_at')->orWhereDate('ends_at', '>=', now()->toDateString());
        });
    }

    public function scopeEnded(Builder $query): Builder
    {
        // Parenthesized so the orWhere cannot escape surrounding constraints
        // (e.g. a person_id filter) via operator precedence.
        return $query->where(function (Builder $inner): void {
            $inner->where('status', 'ended')->orWhereNotNull('ends_at');
        });
    }

    public function scopeForPerson(Builder $query, int|Person $person): Builder
    {
        $personId = $person instanceof Person ? $person->id : $person;

        return $query->where('person_id', $personId);
    }
}
