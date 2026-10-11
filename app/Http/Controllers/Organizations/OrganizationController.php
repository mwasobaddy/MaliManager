<?php

namespace App\Http\Controllers\Organizations;

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
            ->with(['plan:id,slug,name', 'tenant.domains'])
            ->withCount('users')
            ->when($sortColumn !== null, fn ($query) => $query->orderBy($sortColumn, $direction))
            ->when($sortColumn === null, fn ($query) => $query->orderByDesc('organizations.created_at'))
            ->orderBy('organizations.id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Organization $organization): array => [
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
            ]);

        return Inertia::render('organizations/index', [
            'organizations' => $organizations,
            // The edit + create sheets render on this page, so they need the
            // same options the standalone pages get.
            'plans' => Plan::orderBy('name')->get(['id', 'name', 'slug']),
            // Every user on the platform, minus platform admins. This is by far
            // the heaviest prop on the page and only the create sheet reads it,
            // so it is optional rather than eager: it is not queried or
            // serialised on a search, sort, page change, or sheet open. The
            // page asks for it explicitly the first time the sheet opens.
            'owners' => Inertia::optional(fn () => User::query()
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', PlatformRole::Admin->value))
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

    public function create(): Response
    {
        return Inertia::render('organizations/create', [
            'plans' => Plan::orderBy('name')->get(['id', 'name', 'slug']),
            'owners' => User::query()
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', PlatformRole::Admin->value))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
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

        return redirect()->route('organizations.index');
    }

    public function edit(Organization $organization): Response
    {
        return Inertia::render('organizations/edit', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'email' => $organization->email,
                'phone' => $organization->phone,
                'status' => $organization->status->value,
                'status_label' => $organization->status->label(),
                'plan_id' => $organization->plan_id,
            ],
            'plans' => Plan::orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $this->service->update($organization, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Organization has been updated.']);

        return redirect()->route('organizations.index');
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

        return redirect()->route('organizations.index');
    }

    /**
     * Base query for the index and the CSV export, so both always agree on
     * which organizations are in scope.
     */
    private function filtered(Request $request): Builder
    {
        return Organization::query()
            ->leftJoin('plans', 'plans.id', '=', 'organizations.plan_id')
            ->when($request->string('search')->trim(), function ($query, string $search) {
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

            return redirect()->route('organizations.index');
        }

        Inertia::flash('toast', [
            'type' => $result['imported'] > 0 ? 'warning' : 'error',
            'message' => $result['imported'].' imported, '.$result['failed'].' failed.',
        ]);

        Inertia::flash('importErrors', $result['errors']);

        return back();
    }
}
