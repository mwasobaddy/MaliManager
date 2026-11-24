<?php

namespace App\Http\Requests\Tenant;

use App\Enums\UtilityChargeKind;
use App\Enums\UtilityType;
use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUtilityChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'property_id' => [
                'required',
                'integer',
                Rule::exists('properties', 'id')->where(
                    fn ($query) => $query->where('organization_id', TenancyContext::organization()?->id),
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'string', Rule::enum(UtilityChargeKind::class)],
            'utility' => ['nullable', Rule::enum(UtilityType::class)],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => ['integer', Rule::exists('units', 'id')->where(fn ($query) => $query->where('property_id', (int) $this->input('property_id')))],
            'amounts' => ['nullable', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
