<?php

namespace App\Http\Controllers\Users;

use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\DestroyUserRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private UserService $service) {}

    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('person:id,first_name,last_name,email,phone,national_id,gender,status')
            ->with('roles:id,name')
            ->when($request->string('search')->trim(), function ($query, string $search) {
                Search::apply($query, $search, ['name', 'email', 'phone']);
            })
            ->when($request->string('status')->toString() !== '', fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->string('role')->toString() !== '', fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', $request->string('role'))))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => [
                'search' => (string) $request->string('search'),
                'status' => (string) $request->string('status'),
                'role' => (string) $request->string('role'),
            ],
            'platformRoles' => $this->platformRoles(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/create', [
            'platformRoles' => $this->platformRoles(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->service->create($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "User {$user->email} has been created."]);

        return redirect()->route('users.index');
    }

    public function edit(User $user): Response
    {
        $user->load(['person', 'roles:id,name']);

        return Inertia::render('users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'roles' => $user->roles->pluck('name')->all(),
                'person' => $user->person ? [
                    'first_name' => $user->person->first_name,
                    'last_name' => $user->person->last_name,
                    'national_id' => $user->person->national_id,
                    'gender' => $user->person->gender,
                    'status' => $user->person->status,
                ] : null,
            ],
            'platformRoles' => $this->platformRoles(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->service->update($user, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User has been updated.']);

        return redirect()->route('users.index');
    }

    public function setStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,inactive,suspended'],
        ]);

        if ($user->id === $request->user()->id && $validated['status'] !== 'active') {
            abort(422, 'You cannot suspend your own account.');
        }

        $this->service->setStatus($user, $validated['status'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User status updated.']);

        return back();
    }

    public function destroy(DestroyUserRequest $request, User $user): RedirectResponse
    {
        $this->service->deleteUser($user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User has been deleted.']);

        return redirect()->route('users.index');
    }

    /**
     * CSV export of all users with their person record and roles.
     */
    public function export(): \Symfony\Component\HttpFoundation\Response
    {
        $rows = User::query()
            ->with(['person', 'roles'])
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

        return response()->streamDownload(fn () => fpassthru($csv), 'users.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function platformRoles(): array
    {
        return array_map(
            fn (PlatformRole $role): array => ['value' => $role->value, 'label' => str($role->value)->replace('-', ' ')->title()],
            PlatformRole::cases(),
        );
    }
}
