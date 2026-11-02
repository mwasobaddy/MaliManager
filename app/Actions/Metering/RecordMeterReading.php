<?php

declare(strict_types=1);

namespace App\Actions\Metering;

use App\Data\Metering\RecordMeterReadingData;
use App\Models\UnitMeterReading;
use App\Models\User;

/**
 * Records one utility reading and derives its consumption.
 *
 * One use case, one public method, no HTTP concepts. The controller does not
 * know how consumption is calculated, and a test can exercise this without a
 * request in sight.
 */
class RecordMeterReading
{
    public function handle(User $user, RecordMeterReadingData $data): UnitMeterReading
    {
        $previous = UnitMeterReading::query()
            ->where('unit_id', $data->unitId)
            ->where('utility', $data->utility->value)
            ->whereDate('read_on', '<', $data->readOn)
            ->orderByDesc('read_on')
            ->orderByDesc('id')
            ->first();

        return UnitMeterReading::create([
            'organization_id' => $data->organizationId,
            'unit_id' => $data->unitId,
            'utility' => $data->utility,
            'reading' => $data->reading,
            'consumption' => $this->consumption((float) $data->reading, $previous),
            'read_on' => $data->readOn,
            'notes' => $data->notes,
            'recorded_by' => $user->id,
        ]);
    }

    /**
     * Consumption since the previous reading, or null when it cannot be known.
     *
     * Null covers two different situations on purpose: no previous reading
     * (the meter has no history yet), and a reading lower than the last one
     * (the meter was replaced or rolled over). Both mean "this figure is not a
     * consumption", and neither should be reported as zero.
     */
    private function consumption(float $reading, ?UnitMeterReading $previous): ?string
    {
        if ($previous === null) {
            return null;
        }

        $delta = $reading - (float) $previous->reading;

        return $delta >= 0 ? number_format($delta, 3, '.', '') : null;
    }
}
