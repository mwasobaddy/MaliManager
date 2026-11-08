<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrowseListingsRequest;
use App\Http\Resources\ListingResource;
use App\Models\Unit;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public unit listings as JSON: the same public scope and filters as the guest
 * browse page, without the page around it.
 */
class ListingController extends Controller
{
    public function index(BrowseListingsRequest $request): AnonymousResourceCollection
    {
        $units = Unit::query()
            ->browse($request->validated())
            ->paginate(12);

        return ListingResource::collection($units);
    }
}
