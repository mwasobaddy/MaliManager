<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who may read a maintenance note.
 *
 * The distinction is load-bearing: a note about a contractor's quote is
 * internal, a note telling the occupant the plumber arrives at 9am is shared.
 * Keeping it as an enum means the default is explicit and a typo cannot quietly
 * publish an internal note.
 */
enum NoteVisibility: string
{
    case Internal = 'internal';
    case Shared = 'shared';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal only',
            self::Shared => 'Shared with occupant',
        };
    }
}
