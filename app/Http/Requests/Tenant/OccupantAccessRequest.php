<?php

namespace App\Http\Requests\Tenant;

use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The occupant screens: list, create form, edit form and agreement view.
 *
 * Asserts organization membership here. The property-delegation rule stays in
 * `OccupantController::authorizePropertyAccess()`, which is shared by every
 * action in the controller and takes the resolved property, and the
 * occupant/organization match is checked per action. None of these actions take
 * input.
 */
class OccupantAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = TenancyContext::organization();
        $user = $this->user();

        if ($organization === null || $user === null) {
            return false;
        }

        return $user->isOwnerOf($organization)
            || $user->membershipFor($organization) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
