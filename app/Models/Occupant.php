<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Support\TenancyContext;
use Database\Factories\OccupantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An occupant renting one or more units inside an organization.
 * Identity lives on the linked Person (global, deduped by email/phone)
 * and unit assignments live on the occupant_unit pivot.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $person_id
 * @property string $status
 */
class Occupant extends Model
{
    use HasFactory, LogsTenantActivity, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'person_id',
        'status',
        'created_by',
    ];

    /** @use HasFactory<OccupantFactory> */
    protected static function newFactory(): OccupantFactory
    {
        return OccupantFactory::new();
    }

    /**
     * Sort keys the index accepts. `name` orders by the linked person's
     * name through a subselect so the listing never needs a join that
     * would collide on `id` under pagination. Anything else falls back to
     * newest-first, matching the list's original order.
     */
    public const SORTABLE = [
        'name' => 'name',
        'status' => 'occupants.status',
        'added' => 'occupants.created_at',
    ];

    /**
     * The property-scoped occupant listing: tenancy plus property scoping,
     * then the index's search, status and sort filters.
     *
     * @param  array{search?: string, status?: string, sort?: string, direction?: string}  $filters
     */
    public function scopeTenantIndex(Builder $query, int $organizationId, int $propertyId, array $filters): Builder
    {
        $sortKey = self::SORTABLE[$filters['sort'] ?? ''] ?? null;
        $direction = ($filters['direction'] ?? '') === 'asc' ? 'asc' : 'desc';

        return $query
            ->where('occupants.organization_id', $organizationId)
            ->whereHas('units', fn (Builder $unit) => $unit->where('units.property_id', $propertyId))
            ->when(
                trim((string) ($filters['search'] ?? '')),
                fn (Builder $builder, string $search) => $builder->where(
                    fn (Builder $inner) => $inner
                        ->whereHas('person', fn (Builder $person) => $person->where(
                            fn (Builder $name) => $name
                                ->where('persons.first_name', 'like', "%{$search}%")
                                ->orWhere('persons.last_name', 'like', "%{$search}%")
                                ->orWhere('persons.email', 'like', "%{$search}%")
                                ->orWhere('persons.phone', 'like', "%{$search}%")
                                ->orWhere('persons.national_id', 'like', "%{$search}%"),
                        ))
                        ->orWhereHas('units', fn (Builder $unit) => $unit
                            ->where('units.property_id', $propertyId)
                            ->where('units.name', 'like', "%{$search}%")),
                ),
            )
            ->when(
                ($filters['status'] ?? '') !== '',
                fn (Builder $builder) => $builder->where('occupants.status', (string) $filters['status']),
            )
            ->when(
                $sortKey === 'name',
                fn (Builder $builder) => $builder
                    ->orderBy(Person::select('first_name')->whereColumn('persons.id', 'occupants.person_id'), $direction)
                    ->orderBy(Person::select('last_name')->whereColumn('persons.id', 'occupants.person_id'), $direction),
            )
            ->when(
                $sortKey !== null && $sortKey !== 'name',
                fn (Builder $builder) => $builder->orderBy($sortKey, $direction),
            )
            ->when($sortKey === null, fn (Builder $builder) => $builder->orderByDesc('occupants.created_at'))
            ->orderByDesc('occupants.id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'occupant_unit')
            ->withPivot('created_by')
            ->withTimestamps();
    }

    /**
     * Scope route-model binding to the current organization so an
     * occupant id is only resolved within the active tenancy.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        if (TenancyContext::initialized() && TenancyContext::organization()) {
            $query->where('organization_id', TenancyContext::organization()->id);
        }

        return $query;
    }
}
