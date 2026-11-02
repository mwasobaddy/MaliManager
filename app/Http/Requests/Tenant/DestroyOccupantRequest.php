<?php

namespace App\Http\Requests\Tenant;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DestroyOccupantRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Enforced by `sub-permission:occupant.delete` on the route; this form is not
     * a second gate. The `current_password` rule confirms the actor, it does not
     * authorize the action.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
