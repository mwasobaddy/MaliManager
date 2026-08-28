<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use Database\Factories\LandParcelSectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /**
     * Scope sections to one parcel, excluding soft-deleted rows.
     */
    public function scopeForParcel(Builder $query, LandParcel $parcel): Builder
    {
        return $query->where('land_parcel_id', $parcel->id);
    }
}
