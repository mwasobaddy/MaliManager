<?php

namespace App\Http\Controllers\Guest;

use App\Actions\Onboarding\Concerns\OnboardingHelpers;
use App\Actions\Searches\DeleteSavedSearch;
use App\Actions\Searches\SaveSearch;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrowseListingsRequest;
use App\Http\Requests\DestroySavedSearchRequest;
use App\Http\Requests\StoreSavedSearchRequest;
use App\Models\Lease;
use App\Models\Person;
use App\Models\SavedSearch;
use App\Models\Unit;
use App\Models\User;
use App\Support\ListingPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The guest home: public unit browsing, saved searches and the guest's own
 * leases, plus the invitation to become a landlord.
 *
 * This is the central-domain landing for anyone without a property context —
 * new guests, and staff arriving without one. It reads no tenancy state: the
 * browse list is the public scope, leases come from the user's own person, and
 * everything else is theirs by id.
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

        return Inertia::render('guest/home', [
            // The page types listings flat (property_name, photo_url,
            // trust...), so the mapping lives in ListingPresenter rather
            // than here — the same reason OccupantPresenter exists.
            'units' => ListingPresenter::presentPage($units, $request),
            'leases' => $this->leasesFor($user),
            'savedSearches' => SavedSearch::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->get(['id', 'name', 'filters']),
            'filters' => $request->only(['query', 'min_rent', 'max_rent', 'type', 'sort']),
            'available_types' => ListingPresenter::availableTypes(),
            'canBecomeLandlord' => $user->organizations()->wherePivot('is_owner', true)->exists() === false,
            'workspaces' => $this->workspaces($request, $user),
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

    /**
     * The signed-in user's own leases for the "my leases" card, or an empty
     * collection for pure searchers with no linked person.
     *
     * @return Collection<int, Lease>
     */
    private function leasesFor(User $user): Collection
    {
        if (! $user->person_id || ! ($person = Person::find($user->person_id))) {
            return collect();
        }

        return Lease::forPerson($person)->with(['property:id,name', 'unit:id,name'])->orderByDesc('starts_at')->get();
    }

    /**
     * Organizations the user belongs to — owned or staff — each with a link
     * into its property domain. Central-domain staff see their way to work
     * here instead of a bare guest page.
     *
     * @return array<int, array{organization_id: int, organization_name: string, role: string, url: string}>
     */
    private function workspaces(Request $request, User $user): array
    {
        $scheme = $request->secure() ? 'https' : 'http';

        return $user->organizations()
            ->withPivot('is_owner')
            ->orderBy('organizations.name')
            ->get(['organizations.id', 'organizations.name', 'organizations.tenant_id'])
            ->map(function ($organization) use ($scheme): array {
                $domain = $organization->tenant?->domains()->value('domain');

                return [
                    'organization_id' => $organization->id,
                    'organization_name' => $organization->name,
                    'role' => $organization->pivot->is_owner ? 'owner' : 'staff',
                    'url' => $domain ? "{$scheme}://{$domain}/properties" : route('guest.home'),
                ];
            })
            ->all();
    }
}
