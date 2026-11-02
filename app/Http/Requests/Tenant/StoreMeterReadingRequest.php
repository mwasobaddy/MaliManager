<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Data\Metering\RecordMeterReadingData;
use App\Enums\UtilityType;
use App\Models\Property;
use App\Models\UnitMeterReading;
use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeterReadingRequest extends FormRequest
{
    /**
     * Authorization lives here, not in the controller and not only on the
     * route: the policy is the single place that knows who may record a
     * reading, and the Form Request is where the framework already runs it
     * before validation.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', UnitMeterReading::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = TenancyContext::organization()?->id;

        return [
            'unit_id' => [
                'required',
                'integer',
                // Scoped to the organization: an id belonging to another org
                // is a valid integer and would otherwise pass `exists`.
                Rule::exists('units', 'id')->where(
                    fn ($query) => $query->whereIn(
                        'property_id',
                        Property::query()
                            ->where('organization_id', $organizationId)
                            ->select('id'),
                    ),
                ),
            ],
            'utility' => ['required', Rule::enum(UtilityType::class)],
            'reading' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            // A reading dated in the future would let a consumption figure
            // jump backwards when the real reading lands.
            'read_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_id.exists' => 'That unit does not belong to this organization.',
        ];
    }

    public function toData(): RecordMeterReadingData
    {
        return new RecordMeterReadingData(
            organizationId: (int) TenancyContext::organization()?->id,
            unitId: (int) $this->integer('unit_id'),
            utility: UtilityType::from((string) $this->input('utility')),
            reading: (string) $this->input('reading'),
            readOn: (string) $this->input('read_on'),
            notes: $this->filled('notes') ? (string) $this->input('notes') : null,
        );
    }
}
