<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Guards PATCH platform/roles/{role}/status.
 *
 * Takes a boolean rather than a string because "deactivate" is what the toggle
 * sets; `is_active` is the column, so accepting anything but true/false here
 * would leave the column to be guessed at.
 *
 * Built-in roles are refused in the controller, not here: the rule is about
 * which row was addressed, and a `Rule::in()` on the built-in names would
 * silently accept a *custom* role whose name happened to match none of them.
 *
 * Authorization is handled by the `can:manageRoles` route middleware.
 */
class UpdateRoleStatusRequest extends FormRequest
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
            'is_active' => ['required', 'boolean'],
        ];
    }
}
