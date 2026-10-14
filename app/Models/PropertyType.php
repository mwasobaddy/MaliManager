<?php

namespace App\Models;

use App\Enums\PropertyCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A kind of property, classified as residential or commercial.
 *
 * Platform-wide central configuration: one catalogue for every tenant, curated
 * by platform admins under `property-type.*` permissions. It was originally
 * seeded per organization, which meant 52 rows written once per tenant and the
 * possibility of one owner's edits diverging from another's.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property PropertyCategory $category
 * @property int $sort_order
 * @property string $status
 */
class PropertyType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'sort_order',
        'status',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => PropertyCategory::class,
            'sort_order' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * How many properties still reference this type.
     *
     * Shown in the delete confirmation. Deleting a type in use leaves those
     * properties with a null type rather than cascading the delete, so a
     * mis-click cannot silently unlabel someone's buildings.
     */
    public function usageCount(): int
    {
        return $this->properties()->count();
    }

    /**
     * Slug for a name, matching `DefaultPropertyTypes::slugFor()`.
     */
    public static function slugFor(string $name): string
    {
        return Str::slug($name);
    }

    /**
     * Bound by id, not slug.
     *
     * Properties point at a type with `property_type_id`, so id is the key the
     * rest of the app already speaks. The slug is a lookup key for storage
     * paths and CSV imports, not a URL identifier — binding on it would mean a
     * rename 404s every link already shared.
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
