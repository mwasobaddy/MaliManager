<?php

declare(strict_types=1);

namespace App\Data\Metering;

use App\Enums\UtilityType;

/**
 * The boundary object between the Form Request and the Action.
 *
 * `reading` is a string, not a float: the column is `decimal(14,3)` and a
 * binary float cannot represent 1234.567 exactly, so casting on the way in
 * would introduce drift in a number that gets subtracted from another reading.
 */
final readonly class RecordMeterReadingData
{
    public function __construct(
        public int $organizationId,
        public int $unitId,
        public UtilityType $utility,
        public string $reading,
        public string $readOn,
        public ?string $notes = null,
    ) {}
}
