<?php

namespace App\Http\Requests\Tenant;

use App\Concerns\PasswordValidationRules;
use App\Support\TenancyContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting one section of a land parcel.
 *
 * Asserts organization membership; `authorizeParcelAccess()` in the controller
 * still owns the parcel-level delegation rule and the section/parcel match,
 * because both need the resolved route models.
 */
class DestroyLandParcelSectionRequest extends FormRequest
{
    use PasswordValidationRules;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
