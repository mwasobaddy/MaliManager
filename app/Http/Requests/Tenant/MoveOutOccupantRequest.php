<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class MoveOutOccupantRequest extends FormRequest
{
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
