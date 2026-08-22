<?php

namespace App\Models;

use App\Support\TenancyContext;
use Database\Factories\LandParcelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A standalone plot of land an organization manages (title deed,
 * acreage, zoning). Distinct from a Property (building) and listed
 * alongside properties in the asset picker.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property string|null $title_deed_number
 * @property float|null $acreage
 * @property string $zoning
 * @property string|null $address
 * @property string|null $city
 * @property string $status
 * @property float|null $latitude
 * @property float|null $longitude
 * @property bool $available_for_lease
 * @property string|null $notes
 * @property int|null $created_by
 */
class LandParcel extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'title_deed_number',
        'acreage',
        'zoning',
        'address',
        'city',
        'status',
        'latitude',
        'longitude',
        'available_for_lease',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'acreage' => 'decimal:2',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'available_for_lease' => 'boolean',
        ];
    }

    /** @use HasFactory<LandParcelFactory> */
    protected static function newFactory(): LandParcelFactory
    {
        return LandParcelFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Scope route-model binding to the current organization so slugs are
     * only resolved within the active tenancy.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        if (TenancyContext::initialized() && TenancyContext::organization()) {
            $query->where('organization_id', TenancyContext::organization()->id);
        }

        return $query;
    }

    public static function uniqueSlug(int $organizationId, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (static::withTrashed()->where('organization_id', $organizationId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.($suffix++);
        }

        return $slug;
    }
}
