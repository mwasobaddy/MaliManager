<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Platform user filters, sort and focused row.
 *
 * `role` filters by role name; `sort` is shape-checked because the controller
 * allowlists it and falls back to newest-first.
 */
class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Read-only list; the route is behind the platform admin middleware.
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
            'role' => ['nullable', 'string', 'max:191'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'user' => ['nullable', 'integer'],
        ];
    }
}
