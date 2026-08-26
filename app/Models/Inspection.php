<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A unit inspection: photos plus inspector notes, with an optional
 * AI-generated structured condition report.
 */
class Inspection extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    public const MAX_PHOTOS = 10;

    public function registerMediaCollections(): void
    {
        // Inspection photos, stored under the property's inspections folder.
        $this->addMediaCollection('photos');
    }

    protected $fillable = [
        'organization_id',
        'property_id',
        'unit_id',
        'title',
        'inspection_date',
        'notes',
        'ai_report',
        'report_generated_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
            'ai_report' => 'array',
            'report_generated_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
