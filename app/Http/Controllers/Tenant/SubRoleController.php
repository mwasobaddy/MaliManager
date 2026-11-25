<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Staff\CreateSubRole;
use App\Actions\Staff\DeleteSubRole;
use App\Actions\Staff\UpdateSubRole;
use App\Enums\SubPermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroySubRoleRequest;
use App\Http\Requests\Tenant\StoreSubRoleRequest;
use App\Http\Requests\Tenant\SubRoleIndexRequest;
use App\Http\Requests\Tenant\UpdateSubRoleRequest;
use App\Models\SubRole;
use App\Support\DefaultSubRoles;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organization sub-roles: the landlord's named permission bundles.
 * Caretaker and agent ship as defaults, but the landlord shapes them here
 * and creates narrower roles (a plumber who only sees maintenance).
 */
class SubRoleController extends Controller
{
    public function index(SubRoleIndexRequest $request): Response
    {
        $organization = TenancyContext::organization();

        $search = $request->string('search')->trim()->toString();
        $type = $request->string('type')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $query = SubRole::query()
            ->where('organization_id', $organization->id)
            ->withCount('members');

        if ($search !== '') {
            $query->where(function ($constraints) use ($search): void {
                $constraints->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($type === 'builtin') {
            $query->whereIn('slug', array_keys(DefaultSubRoles::bundles()));
        } elseif ($type === 'custom') {
            $query->whereNotIn('slug', array_keys(DefaultSubRoles::bundles()));
        }

        // Sort keys are allowlisted: anything else falls back to name
        // order rather than erroring the page on a hand-edited query.
        $query->when(
            $sort === 'members',
            fn ($query) => $query->orderBy('members_count', $direction)->orderBy('name'),
            fn ($query) => $query->orderBy('name', $direction === 'asc' ? 'asc' : 'desc'),
        );

        $roles = $query
            ->with(['subPermissions:id,key'])
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SubRole $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_locked' => $role->slug === 'landlord',
                'is_builtin' => in_array($role->slug, array_keys(DefaultSubRoles::bundles()), true),
                'members_count' => $role->members_count ?? 0,
                'permissions' => $role->subPermissions
                    ->map(fn ($permission): array => [
                        'value' => $permission->key,
                        'label' => SubPermissionKey::from($permission->key)->label(),
                    ])
                    ->all(),
            ]);

        return Inertia::render('organisation/staff/roles', [
            'roles' => $roles,
            'permissionCatalog' => $this->permissionCatalog(),
        ]);
    }

    /**
     * @return list<array{module: string, module_label: string, permissions: list<array{value: string, label: string}>}>
     */
    private function permissionCatalog(): array
    {
        return collect(SubPermissionKey::cases())
            ->groupBy(fn (SubPermissionKey $key): string => $key->module())
            ->map(fn ($keys, $module): array => [
                'module' => $module,
                'module_label' => $keys->first()->moduleLabel(),
                'permissions' => $keys->map(fn (SubPermissionKey $key): array => [
                    'value' => $key->value,
                    'label' => $key->label(),
                ])->all(),
            ])
            ->values()
            ->all();
    }

    public function store(StoreSubRoleRequest $request, CreateSubRole $create): RedirectResponse
    {
        $organization = TenancyContext::organization()->refresh();

        $create->handle($request->user(), $organization, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role created.']);

        return back();
    }

    public function update(UpdateSubRoleRequest $request, SubRole $subRole, UpdateSubRole $update): RedirectResponse
    {
        $organization = TenancyContext::organization()->refresh();

        abort_unless($subRole->organization_id === $organization->id, 404);

        $update->handle($request->user(), $organization, $subRole, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role updated.']);

        return back();
    }

    public function destroy(DestroySubRoleRequest $request, SubRole $subRole, DeleteSubRole $delete): RedirectResponse
    {
        $organization = TenancyContext::organization()->refresh();

        abort_unless($subRole->organization_id === $organization->id, 404);

        $delete->handle($request->user(), $organization, $subRole);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role deleted.']);

        return back();
    }
}
