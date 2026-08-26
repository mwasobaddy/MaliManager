<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyMaintenanceRequest as DestroyRequest;
use App\Http\Requests\Tenant\UpdateMaintenanceRequest;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\MaintenanceRequestService;
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
    public function index(Request $request, MaintenanceRequestService $service): Response
    {
        $status = $request->string('status')->toString();
        $priority = $request->string('priority')->toString();

        $requests = MaintenanceRequest::query()
            ->where('organization_id', TenancyContext::organization()?->id)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
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

        return Inertia::render('maintenance/index', [
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
        abort_if($record->organization_id !== TenancyContext::organization()?->id, 403);

        $service->update($record, $updateRequest->user(), $updateRequest->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request updated.']);

        return back();
    }

    public function destroy(DestroyRequest $destroyRequest, MaintenanceRequest $record, MaintenanceRequestService $service): RedirectResponse
    {
        abort_if($record->organization_id !== TenancyContext::organization()?->id, 403);

        $service->softDelete($record);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request removed.']);

        return redirect()->route('tenant.maintenance.index');
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
