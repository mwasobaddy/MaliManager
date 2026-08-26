<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Support\StorageLayout;
use App\Support\TenancyContext;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A building or land parcel an organization manages.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property string|null $address
 * @property string|null $city
 * @property string $status
 * @property array|null $settings
 * @property int|null $created_by
 */
class Property extends Model
{
    use HasFactory, LogsTenantActivity, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'address',
        'city',
        'status',
        'settings',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Every new property gets its lease document folder on the
        // configured disk (local in development, S3 in production).
        static::created(function (Property $property): void {
            StorageLayout::bootstrapProperty($property);
        });
    }

    /** @use HasFactory<PropertyFactory> */
    protected static function newFactory(): PropertyFactory
    {
        return PropertyFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
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

    /**
     * Generate a unique slug for the property scoped to its organization.
     */
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
