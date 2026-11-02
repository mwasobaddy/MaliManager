<?php

namespace App\Http\Requests\Tenant;

use App\Enums\PlatformRole;
use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The property picker, the create form and the property dashboard.
 *
 * Allows owners, organization members and platform admins. The admin case is
 * deliberate: the picker has always rendered for a platform admin inside a
 * tenant, and the test suite pins it. This is only a floor — the two stricter
 * rules stay in the controller because they need the resolved organization or
 * property: `authorizePropertyMutation()` (owner-only, for creating) and
 * `authorizePropertyAccess()` (owner or delegated staff, for the dashboard).
 * None of these actions take input.
 */
class PropertyAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = TenancyContext::organization();
        $user = $this->user();

        if ($organization === null || $user === null) {
            return false;
        }

        return $user->isOwnerOf($organization)
            || $user->hasRole(PlatformRole::Admin->value)
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
