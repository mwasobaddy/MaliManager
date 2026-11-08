<?php

namespace App\Http\Requests\Tenant;

use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The land parcel create form and the parcel detail and edit screens.
 *
 * Asserts organization membership. Parcel-level delegation stays in
 * `authorizeAccess()`, which needs the resolved route model, and the route
 * middleware already gates create/edit/delete by sub-permission.
 */
class LandParcelFormRequest extends FormRequest
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
