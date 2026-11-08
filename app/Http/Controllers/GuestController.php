<?php

namespace App\Http\Controllers;

use App\Actions\Onboarding\Concerns\OnboardingHelpers;
use App\Http\Requests\BrowseListingsRequest;
use App\Http\Requests\DestroySavedSearchRequest;
use App\Http\Requests\StoreSavedSearchRequest;
use App\Models\Lease;
use App\Models\Person;
use App\Models\SavedSearch;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->publiclyListed()
            ->with(['property:id,name,organization_id', 'property.organization:id,name'])
            ->when($request->string('query')->trim()->toString(), fn ($query, string $term) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('type', 'like', "%{$term}%"))
            ->when($request->filled('min_rent'), fn ($query) => $query->where('monthly_rent', '>=', $request->input('min_rent')))
            ->when($request->filled('max_rent'), fn ($query) => $query->where('monthly_rent', '<=', $request->input('max_rent')))
            ->when($request->string('type')->toString() !== '', fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->orderByDesc('units.id')
            ->paginate(12)
            ->withQueryString();

        $leases = $user->person_id && ($person = Person::find($user->person_id))
            ? Lease::forPerson($person)->with(['property:id,name', 'unit:id,name'])->orderByDesc('starts_at')->get()
            : collect();

        return Inertia::render('guest/home', [
            'units' => $units,
            'leases' => $leases,
            'savedSearches' => SavedSearch::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->get(['id', 'name', 'filters']),
            'filters' => $request->only(['query', 'min_rent', 'max_rent', 'type']),
            'canBecomeLandlord' => $user->organizations()->wherePivot('is_owner', true)->exists() === false,
            'workspaces' => $this->workspaces($request, $user),
        ]);
    }

    public function storeSearch(StoreSavedSearchRequest $request): RedirectResponse
    {
        $user = $request->user();

        // First tenant-ish action: a guest who saves a search is searching, so
        // the Person identity and Searcher role are created here, lazily,
        // instead of at signup.
        if ($user->person_id === null) {
            $this->createSearcherIdentity($user, [
                'name' => $user->name,
                'phone' => $user->phone,
            ]);

            $user->refresh();
        }

        $user->savedSearches()->create([
            'name' => $request->string('name')->toString(),
            'filters' => $request->validated('filters'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Search saved.']);

        return back();
    }

    public function destroySearch(DestroySavedSearchRequest $request, SavedSearch $search): RedirectResponse
    {
        $search->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Saved search removed.']);

        return back();
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
