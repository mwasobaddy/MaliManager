<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tenant\StoreMaintenanceRequest;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Services\MaintenanceRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Occupant-facing maintenance requests on the central domain: list their
 * own requests and raise new ones against an active lease. The service
 * rejects raisers without an active lease.
 */
class MyMaintenanceController extends Controller
{
    public function index(Request $request, MaintenanceRequestService $service): Response
    {
        $user = $request->user();

        $requests = MaintenanceRequest::query()
            ->where('raised_by', $user->id)
            ->with(['unit:id,name', 'property:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MaintenanceRequest $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'property_name' => $item->property?->name,
                'unit_name' => $item->unit?->name,
                'status' => $item->status,
                'priority' => $item->priority,
                'photo_count' => $item->getMedia('photos')->count(),
                'created_at' => $item->created_at?->toDateString(),
            ]);

        // The active leases available as request targets.
        $activeLeases = Lease::query()
            ->where('person_id', $user->person_id)
            ->where('status', 'active')
            ->whereNull('ends_at')
            ->with(['property:id,name,slug', 'unit:id,name'])
            ->get()
            ->map(fn (Lease $lease): array => [
                'id' => $lease->id,
                'label' => trim(($lease->property?->name ?? '').
                    ($lease->unit?->name ? ' — '.$lease->unit->name : '')),
            ]);

        return Inertia::render('searcher/maintenance', [
            'requests' => $requests,
            'activeLeases' => $activeLeases,
            'priorities' => MaintenanceRequest::PRIORITIES,
        ]);
    }

    public function store(StoreMaintenanceRequest $storeRequest, MaintenanceRequestService $service): RedirectResponse
    {
        $record = $service->create($storeRequest->user(), 0, $storeRequest->validated());

        if ($storeRequest->hasFile('photos')) {
            foreach (array_slice((array) $storeRequest->file('photos'), 0, 3) as $photo) {
                /** @var UploadedFile $photo */
                $record->addMedia($photo)->toMediaCollection('photos');
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Maintenance request submitted.']);

        return redirect()->route('searcher.maintenance.index');
    }
}
