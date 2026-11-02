<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Property-type catalog filters and sort.
 *
 * `sort` is shape-checked only: the controller allowlists it and falls back to
 * the catalog's own display order.
 */
class PropertyTypeIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePropertyTypes') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
