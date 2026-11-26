<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use Database\Factories\LandParcelSectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sub-plot of a LandParcel that can be leased independently. A parcel is
 * "rented in sections": each section carries its own area, status and lease.
 */
class LandParcelSection extends Model
{
    use HasFactory, LogsTenantActivity, SoftDeletes;

    protected $fillable = [
        'land_parcel_id',
        'name',
        'area',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
        ];
    }

    /** @use HasFactory<LandParcelSectionFactory> */
    protected static function newFactory(): LandParcelSectionFactory
    {
        return LandParcelSectionFactory::new();
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(LandParcel::class, 'land_parcel_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'land_parcel_section_id');
    }

    /**
     * The running tenancy on this section, if any. Sections lease one
     * tenant at a time, so the latest active row is the current one.
     */
    public function activeLease(): ?Lease
    {
        return $this->leases()
            ->where('status', 'active')
            ->latest('id')
            ->first();
    }

    /**
     * Scope sections to one parcel, excluding soft-deleted rows.
     */
    public function scopeForParcel(Builder $query, LandParcel $parcel): Builder
    {
        return $query->where('land_parcel_id', $parcel->id);
    }
}
