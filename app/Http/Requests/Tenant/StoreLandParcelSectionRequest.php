<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards POST /{property}/land-parcels/{landParcel}/sections.
 *
 * Parcel-level access depends on the resolved route model and stays in the
 * controller, where `authorizeParcelAccess()` is shared by store, destroy and
 * lease. This only establishes a signed-in user.
 */
class StoreLandParcelSectionRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['vacant', 'leased', 'maintenance'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
