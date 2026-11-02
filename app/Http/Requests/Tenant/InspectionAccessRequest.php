<?php

namespace App\Http\Requests\Tenant;

use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The inspection actions: list, create, show, generate report and delete.
 *
 * Asserts organization membership here; the finer property-delegation rule stays
 * in `InspectionController::authorizePropertyAccess()`, which is shared by every
 * action and takes the resolved property. Duplicating that check would give the
 * same rule two homes and let them drift.
 *
 * None of these actions take input — the inspection comes from the route.
 */
class InspectionAccessRequest extends FormRequest
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
