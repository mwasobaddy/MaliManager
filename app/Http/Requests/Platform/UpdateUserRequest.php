<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female'])],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'suspended'])],
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'string',
                // Custom roles exist now, so this validates against the table
                // rather than the enum. Scoped to active roles: a deactivated
                // role has already moved its holders to `searcher`, and letting
                // it be assigned again would put someone back on a role that is
                // meant to be switched off.
                Rule::exists('roles', 'name')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }
}
