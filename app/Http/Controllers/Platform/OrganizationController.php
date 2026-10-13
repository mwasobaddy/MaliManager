<?php

namespace App\Http\Controllers\Platform;

use App\Enums\OrganizationStatus;
use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\DestroyOrganizationRequest;
use App\Http\Requests\Organizations\ImportOrganizationsRequest;
use App\Http\Requests\Organizations\StoreOrganizationRequest;
use App\Http\Requests\Organizations\UpdateOrganizationRequest;
use App\Http\Requests\Organizations\UpdateOrganizationStatusRequest;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\OrganizationManagementService;
use App\Services\UserService;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function __construct(
        private OrganizationManagementService $service,
        private UserService $userService,
    ) {}

    public function index(Request $request): Response
    {
        // Allowlisted so a crafted `?sort=` can never reach the ORDER BY.
        // `domain` is intentionally absent: it is a host string, not a
        // meaningful ordering key for an admin scanning this list.
        $sortable = [
            'name' => 'organizations.name',
            'plan' => 'plans.name',
            'members' => 'users_count',
            'status' => 'organizations.status',
            'created_at' => 'organizations.created_at',
        ];

        $sort = $request->string('sort')->toString();
        $sortColumn = $sortable[$sort] ?? null;
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $organizations = $this->filtered($request)
            ->with(['plan:id,slug,name', 'tenant.domains', 'owner:id,name,email'])
            ->withCount('users')
            ->when($sortColumn !== null, fn ($query) => $query->orderBy($sortColumn, $direction))
            ->when($sortColumn === null, fn ($query) => $query->orderByDesc('organizations.created_at'))
            ->orderBy('organizations.id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Organization $organization): array => $this->serialize($organization));

        // The row overlay is addressed by URL (`?organization=42`), so it has
        // to work from a shared link as well as from a tap. But the row is
        // normally just `organizations.data.find(...)`, and that only covers
        // the current page — so a link to row 42 lands on "not on the current
        // page of results" whenever 42 is on page 3. Fetch that one row when
        // the page cannot supply it. This is what lets the standalone edit
        // page be a redirect instead of a second copy of the form.
        $focusedId = $request->integer('organization') ?: null;

        $focused = null;

        if ($focusedId !== null
            && ! $organizations->getCollection()->contains('id', $focusedId)) {
            $focused = Organization::query()
                ->with(['plan:id,slug,name', 'tenant.domains', 'owner:id,name,email'])
                ->withCount('users')
                ->find($focusedId);
        }

        return Inertia::render('platform/organizations/index', [
            'organizations' => $organizations,
            'focusedOrganization' => $focused
                ? $this->serialize($focused)
                : null,
            // The edit + create sheets render on this page, so they need the
            // same options the standalone pages got.
            'plans' => Plan::orderBy('name')->get(['id', 'name', 'slug']),
            // Owner candidates. There is no separate "owner" entity — an owner
            // is a User holding the `organization-owner` platform role — so the
            // candidate pool is users. It is narrowed to accounts that could
            // sensibly hold that role: anything carrying a *conflicting*
            // platform role is excluded. Admins are already superusers, and
            // searchers/occupants are end-user personas, not account owners;
            // offering them produced a list of ~80 names led by tenants'
            // renters and standalone staff. Accounts with no platform role, or
            // with `organization-owner`, remain eligible.
            //
            // Optional rather than eager: this is the heaviest prop on the
            // page and only the create sheet reads it, so it is not queried or
            // serialised on a search, sort, page change or sheet open.
            'owners' => Inertia::optional(fn () => User::query()
                ->whereDoesntHave(
                    'roles',
                    fn ($query) => $query->whereIn('name', [
                        PlatformRole::Admin->value,
                        PlatformRole::Searcher->value,
                        PlatformRole::Occupant->value,
                    ])
                )
                ->orderBy('name')
                ->get(['id', 'name', 'email'])),
            'filters' => [
                'search' => (string) $request->string('search'),
                'status' => (string) $request->string('status'),
            ],
            'sort' => [
                'column' => $sortColumn === null ? null : $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * One row shape for both the paginated table and the focused row, so the
     * overlay renders identically whichever way the row was resolved.
     *
     * @return array<string, mixed>
     */
    private function serialize(Organization $organization): array
    {
        // Whether the impersonate button should be offered. The endpoint refuses
        // when the owner is a platform admin, and a button that is enabled and
        // then fails with a toast is worse than one that explains itself.
        $owner = $organization->owner->first();

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'email' => $organization->email,
            'phone' => $organization->phone,
            'status' => $organization->status->value,
            'status_label' => $organization->status->label(),
            'plan' => $organization->plan?->name,
            'plan_id' => $organization->plan_id,
            'domain' => $organization->tenant?->domains->first()?->domain,
            'users_count' => $organization->users_count,
            'created_at' => $organization->created_at?->toDateTimeString(),
            'impersonation_blocked_reason' => $this->impersonationBlockedReason($organization, $owner),
        ];
    }

    /**
     * Why this organization's owner cannot be impersonated, or null if it can.
     *
     * Mirrors the checks in `PlatformController::startImpersonating()` so the UI
     * and the endpoint agree; the endpoint still re-checks, because this is only
     * a hint about what to render.
     */
    private function impersonationBlockedReason(Organization $organization, ?User $owner): ?string
    {
        if ($organization->status === OrganizationStatus::Suspended) {
            return 'Suspended organizations cannot be impersonated.';
        }

        if (! $organization->tenant?->domains->first()) {
            return 'This organization has no domain configured yet.';
        }

        if (! $owner) {
            return 'This organization has no owner to impersonate.';
        }

        if ($owner->hasRole(PlatformRole::Admin->value)) {
            return 'Admins cannot be impersonated. This organization\'s owner is a platform admin.';
        }

        return null;
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $actor = $request->user();

        $owner = $validated['owner_mode'] === 'existing'
            ? User::findOrFail($validated['owner_user_id'])
            : $this->userService->create([
                'first_name' => $validated['owner_first_name'],
                'last_name' => $validated['owner_last_name'] ?? null,
                'email' => $validated['owner_email'],
                'phone' => $validated['owner_phone'] ?? null,
                'status' => 'active',
                'roles' => [PlatformRole::OrganizationOwner->value],
            ], $actor);

        $organization = $this->service->create(
            owner: $owner,
            name: $validated['name'],
            plan: Plan::findOrFail($validated['plan_id']),
            email: $validated['email'] ?? null,
            phone: $validated['phone'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Organization {$organization->name} has been created."]);

        return redirect()->route('platform.organizations.index');
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $this->service->update($organization, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Organization has been updated.']);

        return redirect()->route('platform.organizations.index');
    }

    /**
     * Suspend or reactivate an organization.
     *
     * Mirrors `users/{user}/status` so the row action is a real control
     * rather than a display-only flag.
     */
    public function setStatus(UpdateOrganizationStatusRequest $request, Organization $organization): RedirectResponse
    {
        $status = OrganizationStatus::from($request->validated('status'));

        $this->service->setStatus($organization, $status, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $status === OrganizationStatus::Suspended
                ? $organization->name.' has been suspended.'
                : $organization->name.' has been reactivated.',
        ]);

        return back();
    }

    public function destroy(DestroyOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $this->service->deleteOrganization($organization, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Organization has been deleted.']);

        return redirect()->route('platform.organizations.index');
    }

    /**
     * Base query for the index and the CSV export, so both always agree on
     * which organizations are in scope.
     */
    private function filtered(Request $request): Builder
    {
        return Organization::query()
            ->leftJoin('plans', 'plans.id', '=', 'organizations.plan_id')
            // `Request::string()` returns a Stringable and an object is always truthy,
            // so `->when($request->string('search')->trim(), ...)` ran the filter even
            // with no term — binding `like '%%'`, and for a category also `= ''`, which
            // matched nothing and emptied the table. ->toString() gives a real string.
            ->when($request->string('search')->trim()->toString(), function ($query, string $search) {
                Search::apply($query, $search, ['organizations.name', 'organizations.slug', 'organizations.email']);
            })
            ->when(
                $request->string('status')->toString() !== '',
                fn ($query) => $query->where('organizations.status', $request->string('status'))
            );
    }

    /**
     * CSV export.
     *
     * `scope` picks the row set:
     *   - `all`  (default) — every organization matching the active search and
     *     status filters, ignoring pagination;
     *   - `page`          — only the rows on the current page, still filtered.
     *
     * Both share the index filters so the CSV always matches what the user is
     * looking at.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $scope = $request->string('scope')->toString() === 'page' ? 'page' : 'all';
        $perPage = 15;

        $query = $this->filtered($request);

        if ($scope === 'page') {
            $query->forPage((int) $request->integer('page', 1), $perPage);
        }

        $rows = $query
            ->with(['plan', 'tenant'])
            ->withCount('users')
            ->orderBy('id')
            ->get()
            ->map(fn (Organization $organization): array => [
                $organization->id,
                $organization->name,
                $organization->slug,
                $organization->email ?? '',
                $organization->phone ?? '',
                // `status` is cast to the OrganizationStatus enum, which fputcsv
                // cannot stringify — use ->value.
                $organization->status->value,
                $organization->plan?->name ?? '',
                $organization->tenant?->domains()->value('domain') ?? '',
                $organization->users_count,
                $organization->created_at?->toDateTimeString() ?? '',
            ]);

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['ID', 'Name', 'Slug', 'Email', 'Phone', 'Status', 'Plan', 'Domain', 'Users', 'Created at']);
        foreach ($rows as $row) {
            fputcsv($csv, $row);
        }
        rewind($csv);

        $suffix = $scope === 'page' ? '-page'.$request->integer('page', 1) : '-all';

        return response()->streamDownload(
            fn () => fpassthru($csv),
            'organizations'.$suffix.'.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    /**
     * Create organizations from an uploaded CSV.
     *
     * Redirects back keeping the `import` flag when some rows failed, so the
     * import sheet stays open and can render the per-row report.
     */
    public function import(ImportOrganizationsRequest $request): RedirectResponse
    {
        $result = $this->service->import(
            $request->file('file'),
            $request->validated('plan_id'),
            $request->user(),
        );

        if ($result['failed'] === 0) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => $result['imported'] === 1
                    ? '1 organization imported.'
                    : $result['imported'].' organizations imported.',
            ]);

            return redirect()->route('platform.organizations.index');
        }

        Inertia::flash('toast', [
            'type' => $result['imported'] > 0 ? 'warning' : 'error',
            'message' => $result['imported'].' imported, '.$result['failed'].' failed.',
        ]);

        Inertia::flash('importErrors', $result['errors']);

        return back();
    }
}
