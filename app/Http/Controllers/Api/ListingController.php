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
            ->publiclyListed()
            ->with(['property:id,name,organization_id', 'property.organization:id,name'])
            ->when($request->string('query')->trim()->toString(), fn ($query, string $term) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('type', 'like', "%{$term}%"))
            ->when($request->filled('min_rent'), fn ($query) => $query->where('monthly_rent', '>=', $request->input('min_rent')))
            ->when($request->filled('max_rent'), fn ($query) => $query->where('monthly_rent', '<=', $request->input('max_rent')))
            ->when($request->string('type')->toString() !== '', fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->orderByDesc('units.id')
            ->paginate(12);

        return ListingResource::collection($units);
    }
}
