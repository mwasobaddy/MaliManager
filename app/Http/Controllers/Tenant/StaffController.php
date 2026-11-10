<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyStaffRequest;
use App\Http\Requests\Tenant\StoreStaffRequest;
use App\Http\Requests\Tenant\UpdateStaffRequest;
use App\Models\OrganizationUser;
use App\Models\Property;
use App\Models\User;
use App\Services\StaffService;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(Request $request, StaffService $staffService): Response
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

        return Inertia::render('organisation/staff/index', [
            'organization' => $organization->only('id', 'name', 'slug'),
            'staff' => $staff,
            // Create and edit are overlays on this page, addressed by query
            // params — the option lists are only fetched when a sheet opens.
            'sub_roles' => Inertia::optional(
                fn () => $staffService->formOptions($organization)['sub_roles'],
            ),
            'properties' => Inertia::optional(
                fn () => $staffService->formOptions($organization)['properties'],
            ),
            'focusedStaff' => Inertia::optional(
                fn () => $this->focusedStaff($request, $organization, $staffService),
            ),
        ]);
    }

    /**
     * The staff record addressed by URL for the edit sheet, or null. Mirrors
     * the membership shape the edit form expects; unknown ids resolve to
     * nothing and the sheet simply does not open.
     *
     * @return array<string, mixed>|null
     */
    private function focusedStaff(Request $request, mixed $organization, StaffService $staffService): ?array
    {
        $staffId = $request->integer('staff');

        if ($staffId <= 0) {
            return null;
        }

        $staff = User::find($staffId);

        if (! $staff) {
            return null;
        }

        $membership = $staffService->membership($organization, $staff);

        if (! $membership || $membership->is_owner) {
            return null;
        }

        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'phone' => $staff->phone,
            'sub_role_id' => $membership->sub_role_id,
            'property_ids' => $staffService->delegatedPropertyIds($membership),
        ];
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('tenant.staff.index', ['create' => 1]);
    }

    public function store(StoreStaffRequest $request, StaffService $staffService): RedirectResponse
    {
        $organization = TenancyContext::organization();

        $user = $staffService->create($organization, $request->user(), $request->validated());

        activity()->inLog('staff')
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('staff.created')
            ->log('Added staff member '.$user->email);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Staff member added.']);

        return redirect()->route('tenant.staff.index');
    }

    public function edit(User $staff): RedirectResponse
    {
        return redirect()->route('tenant.staff.index', [
            'mode' => 'edit',
            'staff' => $staff->id,
        ]);
    }

    public function update(UpdateStaffRequest $request, User $staff, StaffService $staffService): RedirectResponse
    {
        $organization = TenancyContext::organization();

        $staffService->update($organization, $staff, $request->user(), $request->validated());

        activity()->inLog('staff')
            ->causedBy($request->user())
            ->performedOn($staff)
            ->event('staff.updated')
            ->log('Updated staff member '.$staff->email);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Staff member updated.']);

        return redirect()->route('tenant.staff.index');
    }

    public function destroy(DestroyStaffRequest $request, User $staff, StaffService $staffService): RedirectResponse
    {
        $organization = TenancyContext::organization();

        $staffService->softDelete($organization, $staff, $request->user());

        activity()->inLog('staff')
            ->causedBy($request->user())
            ->performedOn($staff)
            ->event('staff.deleted')
            ->log('Removed staff member '.$staff->email);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Staff member removed.']);

        return redirect()->route('tenant.staff.index');
    }
}
