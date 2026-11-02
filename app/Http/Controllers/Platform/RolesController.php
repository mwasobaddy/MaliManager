<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Actions\Roles\ExportRoles;
use App\Actions\Roles\ImportRoles;
use App\Concerns\FlashesImportResult;
use App\Enums\PlatformPermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\DestroyRoleRequest;
use App\Http\Requests\Platform\ExportRolesRequest;
use App\Http\Requests\Platform\ImportRoleRequest;
use App\Http\Requests\Platform\RoleIndexRequest;
use App\Http\Requests\Platform\StoreRoleRequest;
use App\Http\Requests\Platform\UpdateRolePermissionsRequest;
use App\Http\Requests\Platform\UpdateRoleStatusRequest;
use App\Models\Role;
use App\Services\RoleManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RolesController extends Controller
{
    use FlashesImportResult;

    /**
     * Sort keys the table may order by, mapped to real columns.
     *
     * A map rather than a passthrough: the query string is user input, and
     * `orderBy($request->string('sort'))` would put anything at all there.
     *
     * @var array<string, string>
     */
    private const SORTABLE = [
        'name' => 'name',
        'permissions' => 'id',   // ordering by count is not portable; id is stable
        'users' => 'id',
        'status' => 'is_active',
        'created_at' => 'created_at',
    ];

    public function __construct(private readonly RoleManagementService $roles) {}

    /**
     * The platform roles and the central permissions each one carries.
     *
     * Role assignment lives on the users index, through `PATCH
     * /users/{user}`, which is the one place that validates it. This page used
     * to carry a duplicate user table whose `syncRoles()` *replaced* a user's
     * whole set — a user with two roles silently lost one while a success toast
     * said otherwise.
     *
     * Roles are rows now, not four enum cases: an admin can create custom ones,
     * deactivate them, and delete them. The four built-ins stay protected and
     * editable — `Role::isProtected()` decides, by name, and the service
     * refuses to deactivate or delete one.
     */
    public function index(RoleIndexRequest $request): Response
    {
        $sort = $request->string('sort')->toString();
        $sortColumn = self::SORTABLE[$sort] ?? null;
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $roles = $this->filtered($request)
            ->with('permissions')
            ->withCount('users')
            ->when(
                $sortColumn !== null,
                fn (Builder $query) => $query->orderBy($sortColumn, $direction),
            )
            ->when($sortColumn === null, fn (Builder $query) => $query->orderBy('name'))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Role $role): array => $this->serialize($role));

        return Inertia::render('platform/roles/index', [
            'roles' => $roles,
            'permissionCatalog' => collect(PlatformPermissionKey::cases())
                ->map(fn (PlatformPermissionKey $key): array => [
                    'value' => $key->value,
                    'label' => $key->label(),
                ])
                ->values()
                ->all(),
            'canCreate' => request()->user()?->hasPermissionTo(PlatformPermissionKey::RoleCreate->value) ?? false,
            'canEdit' => request()->user()?->hasPermissionTo(PlatformPermissionKey::ManageRoles->value) ?? false,
            'canDelete' => request()->user()?->hasPermissionTo(PlatformPermissionKey::RoleDelete->value) ?? false,
            'canImport' => request()->user()?->hasPermissionTo(PlatformPermissionKey::RoleImport->value) ?? false,
            'canExport' => request()->user()?->hasPermissionTo(PlatformPermissionKey::RoleExport->value) ?? false,
        ]);
    }

    /**
     * Create a custom role.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $role = $this->roles->create($validated['name'], $validated['permissions'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role \"{$role->name}\" created."]);

        return redirect()->route('platform.roles.index');
    }

    /**
     * Sync the central permissions for a role (password-protected).
     */
    public function update(UpdateRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        $permissions = Permission::whereIn('name', $request->validated('permissions', []))->get()->all();

        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Permissions for {$role->name} updated."]);

        return back();
    }

    /**
     * Activate or deactivate a custom role.
     *
     * Deactivating moves everyone holding it to `searcher`; see
     * `RoleManagementService::setActive()` for why.
     */
    public function setStatus(UpdateRoleStatusRequest $request, Role $role): RedirectResponse
    {
        $isActive = $request->boolean('is_active');

        $this->roles->setActive($role, $isActive);

        $moved = count($this->roles->reassignedUserIds());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $isActive
                ? "Role \"{$role->name}\" activated."
                : "Role \"{$role->name}\" deactivated."
                    .($moved > 0 ? " {$moved} user(s) moved to searcher." : ''),
        ]);

        return back();
    }

    /**
     * Delete a custom role, handing its users to `searcher`.
     */
    public function destroy(DestroyRoleRequest $request, Role $role): RedirectResponse
    {
        $name = $role->name;

        $this->roles->delete($role);

        $moved = count($this->roles->reassignedUserIds());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Role \"{$name}\" deleted."
                .($moved > 0 ? " {$moved} user(s) moved to searcher." : ''),
        ]);

        return redirect()->route('platform.roles.index');
    }

    /**
     * CSV export of the roles, honouring the table's own filters.
     *
     * Permissions are pipe-separated, matching the import format, so a file can
     * be exported, edited by hand and imported back.
     */
    public function export(ExportRolesRequest $request, ExportRoles $export): StreamedResponse
    {
        $csv = $export->handle($request->toData());

        return response()->streamDownload(
            function () use ($csv): void {
                echo $csv->content;
            },
            $csv->filename,
            ['Content-Type' => 'text/csv'],
        );
    }

    /**
     * Create custom roles from an uploaded CSV.
     *
     * Rows are independent: a bad row is recorded and skipped while the good
     * rows still land.
     *
     * Recognised columns (matched case-insensitively, spaces or underscores):
     * Name (required), Permissions (pipe-separated), Status.
     *
     * A row naming a built-in role is rejected rather than skipped — it is the
     * case where an operator's intent and the file disagree, and silently
     * ignoring it would import the file as a success while doing less than asked.
     */
    public function import(ImportRoleRequest $request, ImportRoles $import): RedirectResponse
    {
        $result = $import->handle($request->toData());

        return $this->respondToImport($result, 'role', 'roles', 'platform.roles.index');
    }

    /**
     * @return Builder<Role>
     */
    private function filtered(Request $request): Builder
    {
        return Role::query()->filtered([
            'search' => $request->string('search')->trim()->toString(),
            'status' => $request->string('status')->toString(),
            'type' => $request->string('type')->toString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => str($role->name)->replace('-', ' ')->title()->toString(),
            'is_active' => (bool) $role->is_active,
            'is_protected' => $role->isProtected(),
            'users_count' => $role->users_count ?? 0,
            'permissions_count' => $role->permissions->count(),
            'permissions' => $role->permissions
                ->map(fn (Permission $permission): array => [
                    'value' => $permission->name,
                    'label' => $this->permissionLabel($permission->name),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The human label for a permission key, falling back to the raw key for
     * anything a role holds that the enum no longer declares.
     */
    private function permissionLabel(string $value): string
    {
        foreach (PlatformPermissionKey::cases() as $key) {
            if ($key->value === $value) {
                return $key->label();
            }
        }

        return $value;
    }
}
