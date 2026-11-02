<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The occupant-form template picker for one property.
 *
 * Read-only and parameter-free. The property access check stays in
 * `AgreementTemplateController::authorizePropertyAccess()`, which is shared by
 * every action in that controller and takes the resolved route model; this only
 * establishes a signed-in user.
 */
class TemplatePickerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
