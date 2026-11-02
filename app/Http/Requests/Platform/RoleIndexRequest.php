<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Role list filters and sort.
 *
 * `type` distinguishes the built-in roles from custom ones; the controller
 * expresses that split in SQL. `sort` is shape-checked only, because the
 * controller allowlists it and falls back to name order.
 */
class RoleIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageRoles') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:64'],
            'type' => ['nullable', 'string', 'max:64'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
