<?php

namespace App\Http\Controllers\Guest;

use App\Actions\Onboarding\Concerns\OnboardingHelpers;
use App\Actions\Searches\DeleteSavedSearch;
use App\Actions\Searches\SaveSearch;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrowseListingsRequest;
use App\Http\Requests\DestroySavedSearchRequest;
use App\Http\Requests\StoreSavedSearchRequest;
use App\Models\SavedSearch;
use App\Models\Unit;
use App\Support\ListingPresenter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The guest home: public unit browsing and saved searches, plus the
 * invitation to become a landlord.
 *
 * This is the central-domain landing for anyone without a property context —
 * new guests, and staff arriving without one. It reads no tenancy state: the
 * browse list is the public scope and everything else is theirs by id.
 * Navigation (workspaces, recent searches) lives in the sidebar via shared
 * props; leases live on the rentals page.
 */
class GuestController extends Controller
{
    use OnboardingHelpers;

    public function home(BrowseListingsRequest $request): Response
    {
        $user = $request->user();

        $units = Unit::query()
            ->browse($request->validated())
            ->paginate(12)
            ->withQueryString();

        // Cast to object: an empty $request->only() is a PHP list, which
        // JSON-encodes as [] — and [].sort is Array.prototype.sort, so an
        // unfiltered page would hand every reader a native function.
        return Inertia::render('guest/home', [
            // The page types listings flat (property_name, photo_url,
            // trust...), so the mapping lives in ListingPresenter rather
            // than here — the same reason OccupantPresenter exists.
            'units' => ListingPresenter::presentPage($units, $request),
            'savedSearches' => SavedSearch::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->get(['id', 'name', 'filters']),
            'filters' => (object) $request->only(['query', 'min_rent', 'max_rent', 'type', 'sort']),
            'available_types' => ListingPresenter::availableTypes(),
            'canBecomeLandlord' => $user->organizations()->wherePivot('is_owner', true)->exists() === false,
        ]);
    }

    public function storeSearch(StoreSavedSearchRequest $request, SaveSearch $save): RedirectResponse
    {
        $save->handle(
            $request->user(),
            $request->string('name')->toString(),
            $request->validated('filters'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Search saved.']);

        return back();
    }

    public function destroySearch(DestroySavedSearchRequest $request, SavedSearch $search, DeleteSavedSearch $delete): RedirectResponse
    {
        $delete->handle($search);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Saved search removed.']);

        return back();
    }

    /**
     * One public listing. The public scope is the gate: anything held back
     * or unpublished 404s instead of leaking through the id.
     */
    public function show(int $unit): Response
    {
        $listing = Unit::query()
            ->publiclyListed()
            ->with(['property:id,name,city,organization_id', 'property.organization:id,name,settings,created_at', 'media'])
            ->findOrFail($unit);

        $organization = $listing->property->organization;

        return Inertia::render('guest/listing', [
            'unit' => [
                'id' => $listing->id,
                'name' => $listing->name,
                'type' => $listing->type,
                'monthly_rent' => $listing->monthly_rent,
                'deposit' => $listing->deposit,
                'currency' => $organization?->settings['currency'] ?? 'KES',
                'photos' => $listing->photoPayload(),
                'property_name' => $listing->property?->name,
                'property_city' => $listing->property?->city,
                'organization_name' => $organization?->name,
                'landlord_since' => $organization?->created_at?->year,
                'trust' => $listing->trustBadges(),
            ],
        ]);
    }
}
