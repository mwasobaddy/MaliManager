<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyMaintenanceRequest as DestroyRequest;
use App\Http\Requests\Tenant\MaintenanceIndexRequest;
use App\Http\Requests\Tenant\UpdateMaintenanceRequest;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\MaintenanceRequestService;
use App\Services\StaffService;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff-side maintenance workflow: org-wide list with status/priority
 * filters, assignment, status transitions, and deletion.
 */
class MaintenanceController extends Controller
{
    public function index(MaintenanceIndexRequest $request, MaintenanceRequestService $service): Response
    {
        $status = $request->string('status')->toString();
        $priority = $request->string('priority')->toString();
        $organization = TenancyContext::organization();

        $requests = MaintenanceRequest::query()
            ->where('organization_id', $organization?->id)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
            ->when(! $request->user()->isOwnerOf($organization), function ($query) use ($request, $organization) {
                $query->whereIn(
                    'property_id',
                    app(StaffService::class)->delegatedPropertyIds($request->user()->membershipFor($organization)),
                );
            })
            ->with(['unit:id,name', 'assignee:id,name', 'raiser:id,name'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (MaintenanceRequest $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'unit_name' => $item->unit?->name,
                'raised_by' => $item->raiser?->name,
                'assigned_to' => $item->assigned_to,
                'assignee_name' => $item->assignee?->name,
                'status' => $item->status,
                'priority' => $item->priority,
                'resolution_notes' => $item->resolution_notes,
                'photo_count' => $item->getMedia('photos')->count(),
                'allowed_transitions' => array_values($service->transitionsFrom($item->status)),
            ]);

        return Inertia::render('organisation/maintenance/index', [
            'requests' => $requests,
            'filters' => [
                'status' => $status,
                'priority' => $priority,
            ],
            'statuses' => MaintenanceRequest::STATUSES,
            'priorities' => MaintenanceRequest::PRIORITIES,
            'staff' => $this->staff(),
        ]);
    }

    public function update(UpdateMaintenanceRequest $updateRequest, MaintenanceRequest $record, MaintenanceRequestService $service): RedirectResponse
    {
        $this->authorizeRecordAccess($updateRequest, $record);

        $service->update($record, $updateRequest->user(), $updateRequest->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request updated.']);

        return back();
    }

    public function destroy(DestroyRequest $destroyRequest, MaintenanceRequest $record, MaintenanceRequestService $service): RedirectResponse
    {
        $this->authorizeRecordAccess($destroyRequest, $record);

        $service->softDelete($record);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request removed.']);

        return redirect()->route('tenant.maintenance.index');
    }

    /**
     * Staff may only touch requests on properties they are delegated to;
     * owners can work every property in the organization.
     */
    private function authorizeRecordAccess(Request $request, MaintenanceRequest $record): void
    {
        $organization = TenancyContext::organization();

        abort_if($record->organization_id !== $organization?->id, 403);

        if ($organization === null || $request->user()->isOwnerOf($organization)) {
            return;
        }

        abort_unless(
            $record->property_id !== null
                && in_array(
                    $record->property_id,
                    app(StaffService::class)->delegatedPropertyIds($request->user()->membershipFor($organization)),
                    true,
                ),
            403,
        );
    }

    /**
     * Active organization members available for assignment.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function staff(): array
    {
        $organization = TenancyContext::organization();

        if (! $organization) {
            return [];
        }

        return User::query()
            ->whereHas('organizations', fn ($query) => $query
                ->whereKey($organization->id)
                ->where('organization_user.status', 'active'))
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }
}
