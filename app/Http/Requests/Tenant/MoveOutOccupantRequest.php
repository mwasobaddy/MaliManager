<?php

namespace App\Http\Requests\Tenant;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MoveOutOccupantRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Enforced by `sub-permission:occupant.edit` on the route; this form is not a
     * second gate. It takes no input, so there is nothing else to check here.
     */
    public function authorize(): bool
    {
        // Enforced by the `sub-permission:occupant.edit` middleware on the route;
        // this form is not a second gate.
        return true;
    }

    /**
     * Moving out ends every lease in the property and deactivates the
     * occupant — as destructive as ending a lease, so the actor confirms
     * with their password the same way.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
