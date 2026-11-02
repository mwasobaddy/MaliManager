<?php

declare(strict_types=1);

namespace App\Http\Resources\Metering;

use App\Models\UnitMeterReading;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The response contract for a meter reading.
 *
 * The model is never returned directly: `reading` and `consumption` are
 * decimal casts (strings), `utility` is an enum, and `read_on` is a Carbon
 * instance — three shapes the frontend would have to know about. This fixes
 * all three in one place.
 *
 * @mixin UnitMeterReading
 */
class UnitMeterReadingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'unit_name' => $this->whenLoaded('unit', fn () => $this->unit?->name),
            'utility' => $this->utility->value,
            'utility_label' => $this->utility->label(),
            'unit_of_measure' => $this->utility->unitOfMeasure(),
            'reading' => (float) $this->reading,
            'consumption' => $this->consumption === null ? null : (float) $this->consumption,
            'read_on' => $this->read_on->toDateString(),
            'notes' => $this->notes,
            'recorded_by' => $this->recorded_by,
            'recorder_name' => $this->whenLoaded('recorder', fn () => $this->recorder?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
