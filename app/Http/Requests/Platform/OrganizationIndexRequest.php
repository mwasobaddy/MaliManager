<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Organization list filters, sort and focused row.
 *
 * `sort` is validated for shape only, not against the controller's allowlist:
 * an unknown key currently falls back to the default order, and turning that
 * into a 422 would break a bookmarked link that still renders correctly.
 */
class OrganizationIndexRequest extends FormRequest
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
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'organization' => ['nullable', 'integer'],
        ];
    }
}
