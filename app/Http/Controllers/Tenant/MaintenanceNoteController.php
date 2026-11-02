<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Maintenance\AddMaintenanceNote;
use App\Enums\NoteVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreMaintenanceNoteRequest;
use App\Http\Resources\Maintenance\MaintenanceNoteResource;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestNote;
use App\Support\InertiaRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notes on a single maintenance request.
 */
class MaintenanceNoteController extends Controller
{
    public function index(MaintenanceRequest $record): Response
    {
        Gate::authorize('viewAny', [MaintenanceRequestNote::class, $record]);

        $record->loadMissing('unit:id,name');

        $notes = MaintenanceRequestNote::query()
            ->where('maintenance_request_id', $record->id)
            ->with('author:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MaintenanceRequestNote $note): array => (new MaintenanceNoteResource($note))->resolve())
            ->all();

        return Inertia::render('organisation/maintenance/notes', [
            'request' => $this->summary($record),
            'notes' => $notes,
            'visibilities' => array_map(
                fn (NoteVisibility $case): array => [
                    'value' => $case->value,
                    'label' => $case->label(),
                ],
                NoteVisibility::cases(),
            ),
        ]);
    }

    public function store(
        StoreMaintenanceNoteRequest $request,
        MaintenanceRequest $record,
        AddMaintenanceNote $addNote,
    ): RedirectResponse {
        $addNote->handle($request->user(), $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Note added.']);

        return InertiaRedirect::to(
            route('tenant.maintenance.notes.index', $record),
            $request,
        );
    }

    /**
     * The header the page renders above the history. Kept out of `index` so the
     * method stays a list-and-render rather than a payload builder.
     *
     * @return array<string, mixed>
     */
    private function summary(MaintenanceRequest $record): array
    {
        return [
            'id' => $record->id,
            'title' => $record->title,
            'status' => $record->status,
            'priority' => $record->priority,
            'unit_name' => $record->unit?->name,
        ];
    }
}
