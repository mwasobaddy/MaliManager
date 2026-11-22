<?php

namespace App\Http\Requests\Platform;

use App\Actions\PropertyTypes\ExportPropertyTypes;
use App\Data\PropertyTypes\ExportPropertyTypesData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Property-type catalogue CSV export.
 *
 * The search and category filters must match the index so "Export all" cannot
 * disagree with the table it was clicked from.
 */
class ExportPropertyTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exportPropertyTypes') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['nullable', Rule::in(['all', 'page'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', Rule::in(ExportPropertyTypes::HEADERS)],
        ];
    }

    public function toData(): ExportPropertyTypesData
    {
        return new ExportPropertyTypesData(
            scope: $this->input('scope') === 'page' ? 'page' : 'all',
            page: max(1, (int) $this->integer('page', 1)),
            search: $this->string('search')->trim()->toString(),
            category: $this->string('category')->toString(),
            columns: array_values(array_filter((array) $this->input('columns', []), 'is_string')),
        );
    }
}
