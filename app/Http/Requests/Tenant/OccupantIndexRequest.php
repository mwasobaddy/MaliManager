<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Occupant list filters for one property.
 *
 * Read-only: `search`, `status` and the sort key narrow the caller's own
 * occupants. `sort` is shape-checked only; the controller allowlists it
 * and falls back to newest-first.
 */
class OccupantIndexRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:64'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
