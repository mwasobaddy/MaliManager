<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\OrganizationStatus;
use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\DestroyOrganizationRequest;
use App\Http\Requests\Organizations\StoreOrganizationRequest;
use App\Http\Requests\Organizations\UpdateOrganizationRequest;
use App\Http\Requests\Organizations\UpdateOrganizationStatusRequest;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\OrganizationManagementService;
use App\Services\UserService;
use App\Support\Search;
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

        $organizations = Organization::query()
            ->with(['plan:id,slug,name', 'tenant.domains'])
            ->withCount('users')
            ->leftJoin('plans', 'plans.id', '=', 'organizations.plan_id')
            ->when($request->string('search')->trim(), function ($query, string $search) {
                Search::apply($query, $search, ['organizations.name', 'organizations.slug', 'organizations.email']);
            })
            ->when($request->string('status')->toString() !== '', fn ($query) => $query->where('organizations.status', $request->string('status')))
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
     * CSV export of all organizations.
     */
    public function export(): \Symfony\Component\HttpFoundation\Response
    {
        $rows = Organization::query()
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
                $organization->status,
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

        return response()->streamDownload(fn () => fpassthru($csv), 'organizations.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
