<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Meter reading list filters.
 *
 * Read-only: `unit_id` picks one meter and `utility` one utility within the
 * organization's own readings. Cross-organization ids cannot leak rows because
 * the query is scoped to the tenancy before the filters apply.
 */
class MeterReadingIndexRequest extends FormRequest
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
            'unit_id' => ['nullable', 'integer', 'min:1'],
            'utility' => ['nullable', 'string', 'max:64'],
        ];
    }
}
