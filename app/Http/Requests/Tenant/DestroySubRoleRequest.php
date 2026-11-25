<?php

namespace App\Http\Requests\Tenant;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class DestroySubRoleRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Enforced by `sub-permission:staff.manage` on the route; this form is not a
     * second gate. The `current_password` rule confirms the actor, it does not
     * authorize the action.
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
