<?php

namespace App\Actions\EditorImages;

use App\Models\EditorImage;
use App\Models\User;
use App\Services\Service;

/**
 * Stores one editor upload as its own row with the file attached.
 *
 * One image per row keeps agreement HTML small: the row holds the served URL
 * and the binary lives in medialibrary, rather than base64 inside the markup.
 */
class StoreEditorImage extends Service
{
    public function handle(int $organizationId, User $author, string $field = 'image'): EditorImage
    {
        return $this->transaction(function () use ($organizationId, $author, $field): EditorImage {
            $image = new EditorImage([
                'organization_id' => $organizationId,
                'created_by' => $author->id,
            ]);

            $this->save($image);

            $image->addMediaFromRequest($field)->toMediaCollection('image');

            return $image->refresh();
        });
    }
}
