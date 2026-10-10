<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Guards POST organizations/{organization}/impersonate. The endpoint takes no
 * input of its own — the target owner is resolved from the organization — so
 * this exists to satisfy the write-endpoint convention; authorization is
 * enforced by the `can:impersonateOrganizations` route middleware.
 */
class ImpersonateOrganizationRequest extends FormRequest
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
