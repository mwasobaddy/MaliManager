<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'units' => ['nullable', 'array', 'max:100'],
            'units.*.name' => ['required', 'string', 'max:255'],
            'units.*.type' => ['nullable', 'string', 'max:255'],
            'units.*.monthly_rent' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
