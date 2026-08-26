<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * An organization- or property-scoped reusable lease agreement template.
 * Bodies may contain {{placeholders}} that are merged from lease data when
 * the agreement is viewed or printed. A ready-made document (PDF/DOCX) can
 * optionally be attached alongside the rich-text body.
 */
class AgreementTemplate extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        // Optional ready-made template document, stored under
        // {organization}/templates/lease/ via TenantPathGenerator.
        $this->addMediaCollection('document')
            ->singleFile()
            ->acceptsMimeTypes([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);
    }

    protected $fillable = [
        'organization_id',
        'property_id',
        'name',
        'body_html',
        'created_by',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
