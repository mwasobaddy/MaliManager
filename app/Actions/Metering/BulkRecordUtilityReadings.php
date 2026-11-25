<?php

declare(strict_types=1);

namespace App\Actions\Metering;

use App\Data\Metering\RecordUtilityReadingData;
use App\Enums\UtilityType;
use App\Models\User;
use App\Models\UtilityReading;
use App\Services\Service;

/**
 * Records a walked route of readings in one transaction: one row per meter,
 * each with exactly the single-record semantics (consumption from the
 * previous reading, null when unknowable). All-or-nothing, so a part-saved
 * route can never bill half a building.
 */
class BulkRecordUtilityReadings extends Service
{
    /**
     * @param  list<array{unit_id: int, utility: string, reading: numeric-string|float|int, read_on: string, notes?: ?string}>  $readings
     * @return list<UtilityReading>
     */
    public function handle(User $actor, int $organizationId, array $readings): array
    {
        abort_if($readings === [], 422, 'Record at least one reading.');

        $record = app(RecordUtilityReading::class);

        return $this->transaction(function () use ($actor, $organizationId, $readings, $record): array {
            $saved = [];

            foreach ($readings as $reading) {
                $saved[] = $record->handle($actor, new RecordUtilityReadingData(
                    organizationId: $organizationId,
                    unitId: (int) $reading['unit_id'],
                    utility: UtilityType::from((string) $reading['utility']),
                    reading: (string) $reading['reading'],
                    readOn: (string) $reading['read_on'],
                    notes: isset($reading['notes']) && $reading['notes'] !== '' ? (string) $reading['notes'] : null,
                ));
            }

            return $saved;
        });
    }
}
