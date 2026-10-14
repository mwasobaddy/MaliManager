<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Guards POST platform/organizations/{organization}/impersonate/{user}.
 *
 * The endpoint takes no input of its own — both the organization and the
 * member are route parameters, and membership is checked in the controller —
 * so this exists to satisfy the write-endpoint convention. Authorization is
 * enforced by the `can:impersonateOrganizations` route middleware.
 */
class ImpersonateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
