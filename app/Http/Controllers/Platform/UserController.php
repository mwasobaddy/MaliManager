<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\DestroyUserRequest;
use App\Http\Requests\Platform\ImportUserRequest;
use App\Http\Requests\Platform\StoreUserRequest;
use App\Http\Requests\Platform\UpdateUserRequest;
use App\Http\Requests\Platform\UpdateUserStatusRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private UserService $service) {}

    /**
     * The shared search/filter base for the table and the CSV export, so
     * "Export all" can never disagree with what the table is showing.
     *
     * @return Builder<User>
     */
    private function filtered(Request $request): Builder
    {
        return User::query()
            ->with('person:id,first_name,last_name,email,phone,national_id,gender,status')
            ->with('roles:id,name')
            // `Request::string()` returns a Stringable and an object is always truthy,
            // so `->when($request->string('search')->trim(), ...)` ran the filter even
            // with no term — binding `like '%%'`, and for a category also `= ''`, which
            // matched nothing and emptied the table. ->toString() gives a real string.
            ->when($request->string('search')->trim()->toString(), function ($query, string $search) {
                Search::apply($query, $search, ['name', 'email', 'phone']);
            })
            ->when($request->string('status')->toString() !== '', fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->string('role')->toString() !== '', fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', $request->string('role'))));
    }

    public function index(Request $request): Response
    {
        // Allowlisted so a crafted `?sort=` can never reach the ORDER BY.
        // Roles are a relation and deliberately absent: sorting by them needs a
        // subquery, and it is not an ordering an admin scans this list for.
        $sortable = [
            'name' => 'name',
            'email' => 'email',
            'status' => 'status',
            'created_at' => 'created_at',
        ];

        $sort = $request->string('sort')->toString();
        $sortColumn = $sortable[$sort] ?? null;
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $users = $this->filtered($request)
            ->when($sortColumn !== null, fn ($query) => $query->orderBy($sortColumn, $direction))
            ->when($sortColumn === null, fn ($query) => $query->orderByDesc('created_at'))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => $this->serialize($user));

        // The row overlay is addressed by URL, so it must work from a shared
        // link and not only from a tap on a visible row. Same fallback as the
        // organizations index: fetch the row only when the page cannot supply it.
        $focusedId = $request->integer('user') ?: null;

        $focused = null;

        if ($focusedId !== null
            && ! $users->getCollection()->contains('id', $focusedId)) {
            $focused = User::query()
                ->with('person:id,first_name,last_name,email,phone,national_id,gender,status')
                ->with('roles:id,name')
                ->find($focusedId);
        }

        return Inertia::render('platform/users/index', [
            'users' => $users,
            'focusedUser' => $focused ? $this->serialize($focused) : null,
            'filters' => [
                'search' => (string) $request->string('search'),
                'status' => (string) $request->string('status'),
                'role' => (string) $request->string('role'),
            ],
            'sort' => [
                'column' => $sortColumn === null ? null : $sort,
                'direction' => $direction,
            ],
            'platformRoles' => $this->platformRoles(),
        ]);
    }

    /**
     * One row shape for both the paginated table and the focused row, so the
     * overlay renders identically whichever way the row was resolved.
     *
     * @return array<string, mixed>
     */
    private function serialize(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'roles' => $user->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])->all(),
            'person' => $user->person ? [
                'id' => $user->person->id,
                'first_name' => $user->person->first_name,
                'last_name' => $user->person->last_name,
                'email' => $user->person->email,
                'phone' => $user->person->phone,
                'national_id' => $user->person->national_id,
                'gender' => $user->person->gender,
                'status' => $user->person->status,
            ] : null,
            'created_at' => $user->created_at?->toDateTimeString(),
        ];
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->service->create($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "User {$user->email} has been created."]);

        return redirect()->route('platform.users.index');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->service->update($user, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User has been updated.']);

        return redirect()->route('platform.users.index');
    }

    public function setStatus(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        if ($user->id === $request->user()->id && $validated['status'] !== 'active') {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'You cannot suspend your own account.']);

            return back();
        }

        $this->service->setStatus($user, $validated['status'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User status updated.']);

        return back();
    }

    public function destroy(DestroyUserRequest $request, User $user): RedirectResponse
    {
        $this->service->deleteUser($user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User has been deleted.']);

        return redirect()->route('platform.users.index');
    }

    /**
     * Create users from an uploaded CSV.
     *
     * Redirects back keeping the `import` flag when some rows failed, so the
     * import sheet stays open and can render the per-row report.
     */
    public function import(ImportUserRequest $request): RedirectResponse
    {
        $result = $this->service->import(
            $request->file('file'),
            $request->user(),
        );

        if ($result['failed'] === 0) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => $result['imported'] === 1
                    ? '1 user imported.'
                    : $result['imported'].' users imported.',
            ]);

            return redirect()->route('platform.users.index');
        }

        Inertia::flash('toast', [
            'type' => $result['imported'] > 0 ? 'warning' : 'error',
            'message' => $result['imported'].' imported, '.$result['failed'].' failed.',
        ]);

        Inertia::flash('importErrors', $result['errors']);

        return back();
    }

    /**
     * Streams the CSV. `scope=page` honours the pagination the user is looking
     * at; anything else exports the whole filtered set.
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
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => [
                $user->id,
                $user->person?->first_name ?? '',
                $user->person?->last_name ?? '',
                $user->email,
                $user->phone ?? '',
                $user->status,
                $user->roles->pluck('name')->implode('|'),
                $user->created_at?->toDateTimeString() ?? '',
            ]);

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['ID', 'First name', 'Last name', 'Email', 'Phone', 'Status', 'Roles', 'Created at']);
        foreach ($rows as $row) {
            fputcsv($csv, $row);
        }
        rewind($csv);

        $suffix = $scope === 'page' ? '-page'.$request->integer('page', 1) : '-all';

        return response()->streamDownload(
            fn () => fpassthru($csv),
            'users'.$suffix.'.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    /**
     * The roles a user can be given: the built-in four plus any active custom
     * role.
     *
     * From the table, not the enum, so a role created on the roles page is
     * assignable without a deploy. Inactive roles are excluded because
     * deactivating one has already moved its holders to `searcher`.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function platformRoles(): array
    {
        return Role::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'value' => $role->name,
                'label' => str($role->name)->replace('-', ' ')->title()->toString(),
            ])
            ->all();
    }
}
