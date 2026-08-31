<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreEditorImageRequest;
use App\Models\EditorImage;
use App\Support\TenancyContext;
use Illuminate\Http\JsonResponse;

/**
 * Accepts an image pasted/inserted into an agreement's rich-text editor,
 * stores it in the tenant's folder via medialibrary and returns a URL the
 * editor embeds in the HTML. Using a dedicated upload (instead of inlining
 * base64) keeps agreement HTML and the database small.
 */
class EditorImageController extends Controller
{
    public function store(StoreEditorImageRequest $request): JsonResponse
    {
        $image = EditorImage::create([
            'organization_id' => TenancyContext::organization()?->id,
            'created_by' => $request->user()->id,
        ]);

        $image->addMediaFromRequest('image')->toMediaCollection('image');

        return response()->json([
            'url' => $image->getFirstMedia('image')->getUrl(),
        ]);
    }
}
