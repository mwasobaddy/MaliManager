<?php

namespace App\Http\Requests\Platform;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guards DELETE platform/roles/{role}.
 *
 * Requires the current password because deleting a role strips it from every
 * user who holds it and hands them the `searcher` fallback — that is a
 * permission change, and per the write-endpoint convention it is guarded the
 * same way as any other destructive action.
 *
 * Authorization is handled by the `can:deleteRoles` route middleware; whether
 * the role is protected is decided in the controller, which is the only place
 * that knows which row it was handed.
 */
class DestroyRoleRequest extends FormRequest
{
    use PasswordValidationRules;

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
            'password' => $this->currentPasswordRules(),
        ];
    }
}
