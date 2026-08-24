<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Support\TenancyContext;
use Database\Factories\OccupantFactory;
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
