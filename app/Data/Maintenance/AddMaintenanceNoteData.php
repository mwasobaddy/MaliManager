<?php

declare(strict_types=1);

namespace App\Data\Maintenance;

use App\Enums\NoteVisibility;

final readonly class AddMaintenanceNoteData
{
    public function __construct(
        public int $maintenanceRequestId,
        public string $body,
        public NoteVisibility $visibility,
    ) {}
}
