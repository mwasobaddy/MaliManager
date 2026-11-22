<?php

namespace App\Http\Requests\Tenant;

use App\Data\Units\ExportUnitsData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Unit CSV export.
 *
 * The search, status, property and sort filters must match the index so
 * "Export all" cannot disagree with the table it was clicked from.
 * Permission rides on `sub-permission:unit.manage`: anyone trusted to
 * browse the units may download the same list.
 */
class ExportUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'status' => ['nullable', 'string', 'max:64'],
            'property' => ['nullable', 'string', 'max:64'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    public function toData(): ExportUnitsData
    {
        return new ExportUnitsData(
            scope: $this->input('scope') === 'page' ? 'page' : 'all',
            page: max(1, (int) $this->integer('page', 1)),
            search: $this->string('search')->trim()->toString(),
            status: $this->string('status')->toString(),
            property: $this->string('property')->toString(),
            sort: $this->string('sort')->toString(),
            direction: $this->string('direction')->toString(),
        );
    }
}
