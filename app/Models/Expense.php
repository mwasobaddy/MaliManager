<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Money spent on an asset: repairs, renovation after move-out, cleaning,
 * utilities, security, etc. Targets a Property or a LandParcel
 * (optionally a specific Unit inside that property).
 */
class Expense extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    /** @var list<string> */
    public const CATEGORIES = [
        'maintenance',
        'renovation',
        'cleaning',
        'utilities',
        'security',
        'other',
    ];

    public function registerMediaCollections(): void
    {
        // Optional receipt, stored under the asset's folder in
        // StorageLayout via TenantPathGenerator.
        $this->addMediaCollection('receipt')
            ->singleFile()
            ->acceptsMimeTypes([
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp',
            ]);
    }

    protected $fillable = [
        'organization_id',
        'expenseable_type',
        'expenseable_id',
        'unit_id',
        'category',
        'amount',
        'currency',
        'spent_on',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_on' => 'date',
        ];
    }

    public function expenseable(): MorphTo
    {
        return $this->morphTo();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
