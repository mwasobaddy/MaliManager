<?php

use App\Actions\Maintenance\AddMaintenanceNote;
use App\Data\Maintenance\AddMaintenanceNoteData;
use App\Enums\NoteVisibility;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestNote;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantService;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    (new PlansSeeder)->run();
});

function actionNoteGraph(): array
{
    $owner = User::factory()->create(['onboarded_at' => now()]);

    $organization = app(TenantService::class)->createOrganization(
        owner: $owner,
        name: 'Action Note Estates',
        plan: Plan::where('slug', 'free')->firstOrFail(),
    );

    $property = $organization->properties()->create([
        'name' => 'Action Note Court',
        'slug' => 'action-note-court',
        'status' => 'active',
        'created_by' => $owner->id,
    ]);

    $request = MaintenanceRequest::create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'title' => 'Broken window',
        'description' => 'Cracked pane in the living room.',
        'status' => 'opened',
        'priority' => 'high',
        'raised_by' => $owner->id,
        'created_by' => $owner->id,
    ]);

    return [$owner, $request];
}

test('the action stores the author, body and visibility', function () {
    [$owner, $request] = actionNoteGraph();

    $note = app(AddMaintenanceNote::class)->handle(
        $owner,
        new AddMaintenanceNoteData(
            maintenanceRequestId: $request->id,
            body: 'Glazier booked for Friday.',
            visibility: NoteVisibility::Shared,
        ),
    );

    expect($note->exists)->toBeTrue()
        ->and($note->author_id)->toBe($owner->id)
        ->and($note->maintenance_request_id)->toBe($request->id)
        ->and($note->visibility)->toBe(NoteVisibility::Shared);
});

test('the action defaults nothing — a missing visibility is a type error, not a silent internal note', function () {
    [$owner, $request] = actionNoteGraph();

    $note = app(AddMaintenanceNote::class)->handle(
        $owner,
        new AddMaintenanceNoteData(
            maintenanceRequestId: $request->id,
            body: 'Internal only.',
            visibility: NoteVisibility::Internal,
        ),
    );

    expect($note->fresh()->visibility)->toBe(NoteVisibility::Internal)
        ->and(MaintenanceRequestNote::internal()->count())->toBe(1);
});
