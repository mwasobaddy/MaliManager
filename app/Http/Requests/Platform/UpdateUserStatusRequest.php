<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards PATCH platform/users/{user}/status.
 *
 * Mirrors `UpdateUserRequest`'s status rule so the row editor and the action
 * cell cannot disagree about what a valid status is. Authorization is handled
 * by the `can:editUsers` route middleware.
 */
class UpdateUserStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'suspended'])],
        ];
    }
}
