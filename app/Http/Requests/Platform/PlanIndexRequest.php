<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Plan list filters, sort and focused row.
 *
 * `sort` is checked for shape only: the controller allowlists it and falls back
 * to the merchandising order for an unknown key, which a stricter rule would
 * turn into a broken link.
 */
class PlanIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Read-only list; the route is behind the platform plan middleware.
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
            'plan' => ['nullable', 'integer'],
        ];
    }
}
