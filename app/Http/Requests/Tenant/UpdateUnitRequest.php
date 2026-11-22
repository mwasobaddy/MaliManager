<?php

namespace App\Http\Requests\Tenant;

use App\Models\Unit;
use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing one unit.
 *
 * Asserts organization membership; the unit's own organization match is still
 * checked in the action, where a cross-organization id answers 404 rather than
 * a generic denial. Permission rides on `sub-permission:unit.edit`.
 * `property_id` is scoped to the tenancy's properties so a unit cannot be
 * filed under another organization's building.
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
            'rent_frequency' => ['required', 'string', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'rent_amount' => ['nullable', 'numeric', 'min:0'],
            'property_id' => [
                'nullable',
                'integer',
                Rule::exists('properties', 'id')->where(
                    'organization_id',
                    TenancyContext::organization()?->id,
                ),
            ],
            'photos' => ['nullable', 'array', 'max:'.Unit::MAX_PHOTOS],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'reuse_photo_ids' => ['nullable', 'array', 'max:'.Unit::MAX_PHOTOS],
            'reuse_photo_ids.*' => ['integer', 'min:1'],
            'cover_photo_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
