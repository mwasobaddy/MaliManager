<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A branch groups an organization's properties and land parcels so
 * multi-branch operators can delegate and filter by branch instead of
 * picking assets one by one.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 */
class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function landParcels(): HasMany
    {
        return $this->hasMany(LandParcel::class);
    }

    /**
     * Unique slug scoped to the organization, mirroring Property.
     */
    public static function uniqueSlug(int $organizationId, string $name): string
    {
        $base = str($name)->slug()->toString();
        $slug = $base;
        $suffix = 1;

        while (static::where('organization_id', $organizationId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.($suffix++);
        }

        return $slug;
    }
}
