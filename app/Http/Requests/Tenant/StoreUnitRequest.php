<?php

namespace App\Http\Requests\Tenant;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'rent_frequency' => ['required', 'string', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'rent_amount' => ['nullable', 'numeric', 'min:0'],
            'photos' => ['nullable', 'array', 'max:'.Unit::MAX_PHOTOS],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'reuse_photo_ids' => ['nullable', 'array', 'max:'.Unit::MAX_PHOTOS],
            'reuse_photo_ids.*' => ['integer', 'min:1'],
            // Optional charge assignments picked on the create sheet. Fixed
            // charges need a per-unit amount; metered and shared carry
            // nothing on the pivot. Ownership is scoped to this property so
            // a crafted id from another building is rejected up front.
            'charges' => ['nullable', 'array'],
            'charges.*.charge_id' => ['required', 'integer', Rule::exists('utility_charges', 'id')->where(fn ($query) => $query->where('property_id', $this->route('property')?->id))],
            'charges.*.amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
