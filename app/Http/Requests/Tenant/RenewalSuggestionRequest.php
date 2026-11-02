<?php

namespace App\Http\Requests\Tenant;

use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Asking for an AI renewal suggestion on one lease.
 *
 * Asserts organization membership; `authorizePropertyAccess()` still owns the
 * property-delegation rule, and the lease's property match and active status are
 * checked in the action where they can answer 403 and 422 respectively rather
 * than a generic denial.
 */
class RenewalSuggestionRequest extends FormRequest
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
