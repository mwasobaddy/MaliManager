<?php

namespace App\Http\Requests\Tenant;

use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organization = TenancyContext::organization();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'sub_role_id' => ['required', 'integer', Rule::exists('sub_roles', 'id')->where('organization_id', $organization?->id)],
            'property_ids' => ['nullable', 'array'],
            'property_ids.*' => ['integer', Rule::exists('properties', 'id')->where('organization_id', $organization?->id)],
        ];
    }
}
