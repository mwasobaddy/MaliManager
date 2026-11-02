<?php

namespace App\Http\Requests\Platform;

use App\Data\PropertyTypes\ImportPropertyTypesData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guards POST platform/property-types/import.
 *
 * Authorization is handled by the `can:importPropertyTypes` route middleware.
 */
class ImportPropertyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('importPropertyTypes') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    public function toData(): ImportPropertyTypesData
    {
        return new ImportPropertyTypesData(file: $this->file('file'));
    }
}
