<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreStaffRequest;
use App\Models\Organization;
use App\Services\StaffService;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Funnel steps 3 and 4: invite the team, then review and launch.
 *
 * These run outside the first-property gate — a fresh organization has no
 * properties yet by definition — but they are owner-only. Staff who wander
 * here are sent to the properties list with an explanation instead of a
 * bare 403.
 */
class SetupController extends Controller
{
    public function team(StaffService $staffService): Response|RedirectResponse
    {
        $organization = $this->ownedOrganization();

        if ($organization instanceof RedirectResponse) {
            return $organization;
        }

        return Inertia::render('setup/team', [
            'organization' => $organization->only('id', 'name', 'slug'),
            'sub_roles' => $staffService->formOptions($organization)['sub_roles'],
        ]);
    }

    public function storeTeam(StoreStaffRequest $request, StaffService $staffService): RedirectResponse
    {
        $organization = $this->ownedOrganization();

        if ($organization instanceof RedirectResponse) {
            return $organization;
        }

        $user = $staffService->create($organization, $request->user(), $request->validated());

        activity()->inLog('staff')
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('staff.created')
            ->log('Added staff member '.$user->email);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Team member invited.']);

        return redirect()->route('tenant.setup.review');
    }

    public function review(): Response|RedirectResponse
    {
        $organization = $this->ownedOrganization();

        if ($organization instanceof RedirectResponse) {
            return $organization;
        }

        return Inertia::render('setup/review', [
            'organization' => [
                ...$organization->only('id', 'name', 'slug'),
                'plan_name' => $organization->plan?->name,
                'currency' => $organization->settings['currency'] ?? 'KES',
            ],
            'properties' => $this->propertySummary($organization),
            'team' => $this->teamSummary($organization),
        ]);
    }

    /**
     * The setup funnel belongs to owners. Staff are redirected with an
     * explanation instead of a bare denial.
     */
    private function ownedOrganization(): Organization|RedirectResponse
    {
        $user = request()->user();
        $organization = TenancyContext::organization();

        if ($organization === null || $user === null || ! $user->isOwnerOf($organization)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only organization owners can do that.']);

            return redirect()->route('tenant.properties.index');
        }

        return $organization;
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string, units_count: int}>
     */
    private function propertySummary(Organization $organization): array
    {
        return $organization->properties()
            ->orderBy('name')
            ->withCount('units')
            ->get(['id', 'name', 'slug'])
            ->map(fn ($property) => [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
                'units_count' => $property->units_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{name: string, email: string, role: string}>
     */
    private function teamSummary(Organization $organization): array
    {
        return $organization->users()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn ($user) => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->is_owner ? 'owner' : 'staff',
            ])
            ->all();
    }
}
