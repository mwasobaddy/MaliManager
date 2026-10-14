<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A rentable unit inside a property.
 *
 * @property int $id
 * @property int $property_id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string|null $type
 * @property float|null $monthly_rent
 * @property float|null $deposit
 * @property array|null $settings
 * @property int|null $created_by
 */
class Unit extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsTenantActivity, SoftDeletes;

    /**
     * Ceiling on unit photos, matching `Inspection::MAX_PHOTOS`. Kept as a
     * constant rather than only a validation rule so the UI can say "12 max"
     * without duplicating the number in two places that can drift.
     */
    public const MAX_PHOTOS = 12;

    protected $fillable = [
        'property_id',
        'name',
        'slug',
        'status',
        'type',
        'monthly_rent',
        'deposit',
        'settings',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_rent' => 'decimal:2',
            'deposit' => 'decimal:2',
            'settings' => 'array',
        ];
    }

    /** @use HasFactory<UnitFactory> */
    protected static function newFactory(): UnitFactory
    {
        return UnitFactory::new();
    }

    /**
     * Derive the slug from the name when one was not supplied.
     *
     * Centralised here rather than in the service layer because `units.slug` is
     * NOT NULL and names the unit's photo folder — every writer has to satisfy
     * it, and there are several: the property create form, the units route,
     * factories, and direct `create()` calls in tests. One guarantee in the
     * model beats the same four lines repeated at each call site, where the
     * first one missed becomes a 500.
     */
    protected static function booted(): void
    {
        static::creating(function (Unit $unit): void {
            if ($unit->slug !== null && $unit->slug !== '') {
                return;
            }

            $unit->slug = $unit->deriveSlug((string) $unit->name);
        });
    }

    /**
     * A slug unique within the unit's property.
     *
     * Unit names repeat across properties by design — "A1" is normal in every
     * building — so uniqueness is scoped to the property, matching the
     * `unique(['property_id', 'slug'])` index. Collisions inside one property
     * get -2, -3 rather than throwing, because adding a second unit called "A1"
     * after a rename elsewhere is ordinary, not an error.
     */
    private function deriveSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'unit', 100, '');
        $slug = $base;
        $suffix = 2;

        // Only disambiguate once the property is actually known; a unit created
        // without one (rare, but possible in a factory) just takes the base.
        while ($this->property_id !== null
            && static::query()
                ->where('property_id', $this->property_id)
                ->where('slug', $slug)
                ->exists()) {
            $slug = Str::limit($base, 100 - strlen((string) $suffix), '').'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function occupants(): BelongsToMany
    {
        return $this->belongsToMany(Occupant::class, 'occupant_unit')
            ->withPivot('created_by')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Unit photos, stored under the unit's own folder — see
     * `StorageLayout::unitPhotosPath()`.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
            ]);
    }

    /**
     * The unit's photos as plain arrays for Inertia.
     *
     * Media models carry a lot of machinery that the page does not need, and
     * shipping them would pull conversions and custom properties into the
     * payload. Only url and file_name, matching what inspections and
     * maintenance already send — `getSize()` would stat the file on disk for
     * every photo on the page, and the browser reports a size anyway when the
     * image fails to load.
     *
     * @return list<array{id: int, url: string, name: string}>
     */
    public function photoPayload(): array
    {
        return $this->getMedia('photos')
            ->map(fn ($media): array => [
                'id' => $media->getKey(),
                'url' => $media->getUrl(),
                'name' => $media->file_name,
            ])
            ->values()
            ->all();
    }
}
