<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reserve or release a land parcel section (vacant <-> reserved).
 * Leased sections end their lease instead; maintenance is edited on the
 * section itself.
 */
class UpdateLandParcelSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['vacant', 'reserved'])],
        ];
    }
}
