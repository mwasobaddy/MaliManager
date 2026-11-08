<?php

namespace App\Support;

use App\Http\Resources\ListingResource;
use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Read-models for the public listing surfaces.
 *
 * The guest page types its units flat (property_name, photo_url, trust...)
 * while Eloquent hands back nested models, so the mapping lives here rather
 * than in the controller — the same reason OccupantPresenter exists. Both
 * the guest page and the public API shape through ListingResource, so the
 * two can never disagree on what a listing looks like.
 */
final class ListingPresenter
{
    /**
     * @param  LengthAwarePaginator<int, Unit>  $units
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public static function presentPage(LengthAwarePaginator $units, Request $request): LengthAwarePaginator
    {
        return $units->through(fn (Unit $unit) => (new ListingResource($unit))->toArray($request));
    }

    /**
     * The unit types a searcher can actually pick: distinct values among
     * currently public listings, so chips never suggest a dead end.
     *
     * @return list<string>
     */
    public static function availableTypes(): array
    {
        return Unit::query()
            ->browse([])
            ->distinct()
            ->whereNotNull('type')
            ->pluck('type')
            ->filter(fn ($type) => trim((string) $type) !== '')
            ->values()
            ->all();
    }
}
