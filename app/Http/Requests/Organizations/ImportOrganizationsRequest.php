<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportOrganizationsRequest extends FormRequest
{
    /**
     * Authorization is handled by the `can:importOrganizations` route middleware.
     */
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
            // `text/plain` covers .csv on most uploads; keep the cap small since
            // every row provisions a tenant, domain and sub-role bundle.
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:2048',
            ],
            'plan_id' => [
                'sometimes',
                'nullable',
                Rule::exists('plans', 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Upload a CSV file (a .txt file containing CSV also works).',
            'file.max' => 'The import file may not be larger than 2 MB.',
        ];
    }
}
