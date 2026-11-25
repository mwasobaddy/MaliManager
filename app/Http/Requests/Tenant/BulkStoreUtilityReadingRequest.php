<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\UtilityType;
use App\Models\Property;
use App\Models\UtilityReading;
use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreUtilityReadingRequest extends FormRequest
{
    /**
     * Same gate as single readings: the policy is the single place that
     * knows a caretaker with unit.manage may record, while rates stay
     * behind utility.manage on the charge routes.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', UtilityReading::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = TenancyContext::organization()?->id;

        return [
            'readings' => ['required', 'array', 'min:1'],
            'readings.*.unit_id' => [
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
            'readings.*.utility' => ['required', Rule::enum(UtilityType::class)],
            'readings.*.reading' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            // A reading dated in the future would let a consumption figure
            // jump backwards when the real reading lands.
            'readings.*.read_on' => ['required', 'date', 'before_or_equal:today'],
            'readings.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'readings.*.unit_id.exists' => 'That unit does not belong to this organization.',
        ];
    }
}
