<?php

namespace App\Http\Requests\Users;

use App\Enums\PlatformRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female'])],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'suspended'])],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::in(array_column(PlatformRole::cases(), 'value'))],
        ];
    }
}
