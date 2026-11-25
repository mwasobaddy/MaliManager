<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Role list filters for one organization. Read-only: `search` matches
 * names, `type` splits built-in bundles from custom roles, and `sort`
 * is shape-checked only; the controller allowlists it.
 */
class SubRoleIndexRequest extends FormRequest
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
            'type' => ['nullable', Rule::in(['builtin', 'custom'])],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
