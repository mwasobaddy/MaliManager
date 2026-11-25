<?php

namespace App\Http\Requests\Tenant;

use App\Enums\UtilityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUtilityChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The kind is immutable after creation: metered↔fixed would invalidate
     * rates against per-unit amounts, so a miscategorized charge is deleted
     * and recreated instead of morphed.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'utility' => ['nullable', Rule::enum(UtilityType::class)],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'allocation' => ['nullable', 'string', Rule::in(['equal', 'occupants'])],
            'master_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'gl_code' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => [
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('property_id', $this->route('charge')?->property_id)),
            ],
            'amounts' => ['nullable', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => (bool) $this->boolean('is_active')]);
        }
    }
}
