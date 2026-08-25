<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],

            'owner_mode' => ['required', 'string', Rule::in(['existing', 'new'])],
            'owner_user_id' => [
                Rule::requiredIf($this->input('owner_mode') === 'existing'),
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],

            'owner_first_name' => [Rule::requiredIf($this->input('owner_mode') === 'new'), 'nullable', 'string', 'max:255'],
            'owner_last_name' => ['nullable', 'string', 'max:255'],
            'owner_email' => [
                Rule::requiredIf($this->input('owner_mode') === 'new'),
                'nullable',
                'string',
                'email',
                'max:255',
            ],
            'owner_phone' => ['nullable', 'string', 'max:30'],

            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
        ];
    }
}
