<?php

declare(strict_types=1);

namespace App\Data\MoveOut;

/**
 * Boundary object between the tenant-facing Form Request and the Action.
 *
 * `moveOutDate` is a Y-m-d string: the form posts a calendar day, and the
 * 30-day rule compares calendar days, not timestamps.
 */
final readonly class SubmitMoveOutNoticeData
{
    public function __construct(
        public int $organizationId,
        public string $moveOutDate,
        public ?string $note,
    ) {}

    public static function minimumDate(): string
    {
        return now()->addDays(30)->toDateString();
    }
}
