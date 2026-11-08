<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;

use App\Actions\Onboarding\Concerns\OnboardingHelpers;
use App\Actions\Searches\DeleteSavedSearch;
use App\Actions\Searches\SaveSearch;
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
            ->browse($request->validated())
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
