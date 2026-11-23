<?php

namespace App\Http\Requests\Tenant;

use App\Actions\Leases\ExportLeases;
use App\Data\Leases\ExportLeasesData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lease CSV export.
 *
 * The search, status, expiring, overdue and sort filters must match the
 * index so "Export all" cannot disagree with the table it was clicked from.
 * Permission rides on `sub-permission:lease.manage`: anyone trusted to
 * browse the leases may download the same list.
 */
class ExportLeasesRequest extends FormRequest
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
            'expiring' => ['nullable', 'boolean'],
            'overdue' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', Rule::in(ExportLeases::HEADERS)],
        ];
    }

    public function toData(): ExportLeasesData
    {
        return new ExportLeasesData(
            scope: $this->input('scope') === 'page' ? 'page' : 'all',
            page: max(1, (int) $this->integer('page', 1)),
            search: $this->string('search')->trim()->toString(),
            status: $this->string('status')->toString(),
            expiring: $this->boolean('expiring'),
            overdue: $this->boolean('overdue'),
            sort: $this->string('sort')->toString(),
            direction: $this->string('direction')->toString(),
            columns: array_values(array_filter((array) $this->input('columns', []), 'is_string')),
        );
    }
}
