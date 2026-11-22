<?php

namespace App\Http\Requests\Tenant;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DestroyUnitRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Enforced by `sub-permission:unit.delete` on the route; this form is not
     * a second gate. The `current_password` rule confirms the actor, it does not
     * authorize the action. Occupancy is refused in the action, not here, so
     * the 422 carries the unit's state rather than a form error.
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
