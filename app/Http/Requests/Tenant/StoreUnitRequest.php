<?php

namespace App\Http\Requests\Tenant;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guards POST {property}/units.
 *
 * Photos are optional and capped at `Unit::MAX_PHOTOS` so a landlord listing a
 * building cannot stall the request with a hundred 8MB uploads. The ceiling is
 * a model constant rather than a literal here, so the form's "12 max" hint and
 * the rule cannot drift apart.
 */
class StoreUnitRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'monthly_rent' => ['nullable', 'numeric', 'min:0'],
            'photos' => ['nullable', 'array', 'max:'.Unit::MAX_PHOTOS],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];
    }
}
