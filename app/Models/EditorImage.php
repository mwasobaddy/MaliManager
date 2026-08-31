<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A lean container that holds one uploaded image for the rich-text editor.
 * When a user pastes or inserts an image in an agreement, the frontend
 * uploads the file here and the returned URL is embedded in the HTML. This
 * keeps pasted images out of the database (no base64 bloat) and places them
 * in the tenant's storage folder via TenantPathGenerator. Rows that are no
 * longer referenced by any agreement are orphaned; pruning them is a future
 * cleanup task.
 */
class EditorImage extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'organization_id',
        'created_by',
    ];

    public function registerMediaCollections(): void
    {
        // One pasted/inserted image per row, stored under
        // {org}/agreement-images via TenantPathGenerator.
        $this->addMediaCollection('image')
            ->singleFile()
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif',
            ]);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
