<?php

namespace App\Http\Requests\Tenant;

use App\Actions\Metering\ExportUtilityReadings;
use App\Data\Metering\ExportUtilityReadingsData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Utility readings CSV export.
 *
 * The unit, utility, search and sort filters must match the index so
 * "Export all" cannot disagree with the table it was clicked from.
 */
class ExportUtilityReadingsRequest extends FormRequest
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
            'unit_id' => ['nullable', 'integer', 'min:1'],
            'utility' => ['nullable', 'string', 'max:64'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', Rule::in(ExportUtilityReadings::HEADERS)],
        ];
    }

    public function toData(): ExportUtilityReadingsData
    {
        return new ExportUtilityReadingsData(
            scope: $this->input('scope') === 'page' ? 'page' : 'all',
            page: max(1, (int) $this->integer('page', 1)),
            unitId: $this->integer('unit_id') ?: null,
            utility: $this->string('utility')->toString(),
            search: $this->string('search')->trim()->toString(),
            sort: $this->string('sort')->toString(),
            direction: $this->string('direction')->toString(),
            columns: array_values(array_filter((array) $this->input('columns', []), 'is_string')),
        );
    }
}
