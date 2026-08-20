<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyStaffRequest;
use App\Http\Requests\Tenant\StoreStaffRequest;
use App\Http\Requests\Tenant\UpdateStaffRequest;
use App\Models\OrganizationUser;
use App\Models\Property;
use App\Models\SubRole;
use App\Models\User;
use App\Services\StaffService;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class StaffController extends Controller
{
    public function index(StaffService $staffService): Response
    {
        $organization = TenancyContext::organization();

        $staff = OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->where('is_owner', false)
            ->where('status', 'active')
            ->with(['user', 'subRole'])
            ->get()
            ->map(fn (OrganizationUser $membership) => [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'phone' => $membership->user->phone,
                'status' => $membership->user->status,
                'sub_role' => $membership->subRole?->only('id', 'name', 'slug'),
                'properties' => Property::query()
                    ->whereIn('id', $staffService->delegatedPropertyIds($membership))
                    ->get(['id', 'name', 'slug']),
            ]);

        return Inertia::render('tenant/staff/index', [
            'organization' => $organization->only('id', 'name', 'slug'),
            'staff' => $staff,
        ]);
    }

    public function create(): Response
    {
        $organization = TenancyContext::organization();

        return Inertia::render('tenant/staff/create', [
            'organization' => $organization->only('id', 'name', 'slug'),
            'sub_roles' => $this->subRoles(),
            'properties' => $this->properties(),
        ]);
    }

    public function store(StoreStaffRequest $request, StaffService $staffService): RedirectResponse
    {
        $organization = TenancyContext::organization();

        try {
            $staffService->create($organization, $request->user(), $request->validated());
        } catch (Throwable $e) {
            Log::error('Failed to create staff member.', [
                'organization_id' => $organization->id,
                'email' => $request->validated('email'),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'email' => 'We could not add this staff member. Please try again.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Staff member added.']);

        return redirect()->route('tenant.staff.index');
    }

    public function edit(User $staff, StaffService $staffService): Response
    {
        $organization = TenancyContext::organization();

        $membership = $staffService->membership($organization, $staff);

        if (! $membership) {
            abort(404);
        }

        return Inertia::render('tenant/staff/edit', [
            'organization' => $organization->only('id', 'name', 'slug'),
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'sub_role_id' => $membership->sub_role_id,
                'property_ids' => $staffService->delegatedPropertyIds($membership),
            ],
            'sub_roles' => $this->subRoles(),
            'properties' => $this->properties(),
        ]);
    }

    public function update(UpdateStaffRequest $request, User $staff, StaffService $staffService): RedirectResponse
    {
        $organization = TenancyContext::organization();

        try {
            $staffService->update($organization, $staff, $request->user(), $request->validated());
        } catch (Throwable $e) {
            Log::error('Failed to update staff member.', [
                'organization_id' => $organization->id,
                'user_id' => $staff->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'email' => 'We could not update this staff member. Please try again.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Staff member updated.']);

        return redirect()->route('tenant.staff.index');
    }

    public function destroy(DestroyStaffRequest $request, User $staff, StaffService $staffService): RedirectResponse
    {
        $organization = TenancyContext::organization();

        try {
            $staffService->softDelete($organization, $staff, $request->user());
        } catch (Throwable $e) {
            Log::error('Failed to delete staff member.', [
                'organization_id' => $organization->id,
                'user_id' => $staff->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'password' => 'We could not delete this staff member. Please try again.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Staff member removed.']);

        return redirect()->route('tenant.staff.index');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function subRoles(): array
    {
        return SubRole::where('organization_id', TenancyContext::organization()->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (SubRole $role) => ['id' => $role->id, 'name' => $role->name])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string}>
     */
    private function properties(): array
    {
        return Property::where('organization_id', TenancyContext::organization()->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Property $property) => ['id' => $property->id, 'name' => $property->name, 'slug' => $property->slug])
            ->all();
    }
}
