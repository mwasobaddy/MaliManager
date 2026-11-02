<?php

declare(strict_types=1);

namespace App\Actions\Maintenance;

use App\Data\Maintenance\AddMaintenanceNoteData;
use App\Models\MaintenanceRequestNote;
use App\Models\User;

/**
 * Attaches a note to a maintenance request.
 *
 * Deliberately not a notification: the note row is the record, and deciding who
 * to tell about it (occupant, assignee) is a listener's job once one exists.
 */
class AddMaintenanceNote
{
    public function handle(User $author, AddMaintenanceNoteData $data): MaintenanceRequestNote
    {
        return MaintenanceRequestNote::create([
            'maintenance_request_id' => $data->maintenanceRequestId,
            'author_id' => $author->id,
            'body' => $data->body,
            'visibility' => $data->visibility,
        ]);
    }
}
