<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreLeaseAdditionalChargeRequest extends FormRequest
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
            'lease_ids' => ['required', 'array', 'min:1'],
            'lease_ids.*' => ['integer'],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'string', Rule::in(['fixed', 'one_time'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'gl_code' => ['nullable', 'string', 'max:32'],
        ];
    }
}
