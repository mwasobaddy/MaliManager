<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting one entry from the central property-type catalog.
 *
 * The catalog is platform-managed and every tenant property form reads it, so
 * the permission is the platform `deletePropertyTypes` gate rather than an
 * ownership check on the row. The route applies the same gate; asserting it
 * here keeps the rule with the action.
 */
class DestroyPropertyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deletePropertyTypes') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
