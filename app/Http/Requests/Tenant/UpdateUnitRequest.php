<?php

namespace App\Http\Requests\Tenant;

use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Editing one unit.
 *
 * Asserts organization membership; the unit's own organization match is still
 * checked in the action, where a cross-organization id answers 404 rather than
 * a generic denial. Permission rides on `sub-permission:unit.edit`.
 */
class UpdateUnitRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'monthly_rent' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
