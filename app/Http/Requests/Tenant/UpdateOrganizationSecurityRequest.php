<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationSecurityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'require_staff_two_factor' => ['nullable', 'boolean'],
            'mode' => ['nullable', Rule::in(['simple', 'pro'])],
            'dismiss_pro_suggestion' => ['nullable', 'boolean'],
        ];
    }
}
