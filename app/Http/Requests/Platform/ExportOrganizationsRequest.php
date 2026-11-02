<?php

namespace App\Http\Requests\Platform;

use App\Data\Organizations\ExportOrganizationsData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Organization CSV export.
 *
 * `scope` picks the row set (`all` ignores pagination, `page` exports the current
 * page) and the search/status filters must match the index, or "export all"
 * would disagree with the table.
 */
class ExportOrganizationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exportOrganizations') ?? false;
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
        ];
    }

    public function toData(): ExportOrganizationsData
    {
        return new ExportOrganizationsData(
            scope: $this->input('scope') === 'page' ? 'page' : 'all',
            page: max(1, (int) $this->integer('page', 1)),
            search: $this->string('search')->trim()->toString(),
            status: $this->string('status')->toString(),
        );
    }
}
