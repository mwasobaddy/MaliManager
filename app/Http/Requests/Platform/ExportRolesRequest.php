<?php

namespace App\Http\Requests\Platform;

use App\Data\Roles\ExportRolesData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Role CSV export.
 *
 * The search, status and built-in/custom filters must match the index so
 * "Export all" cannot disagree with the table it was clicked from.
 */
class ExportRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exportRoles') ?? false;
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
            'type' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function toData(): ExportRolesData
    {
        return new ExportRolesData(
            scope: $this->input('scope') === 'page' ? 'page' : 'all',
            page: max(1, (int) $this->integer('page', 1)),
            search: $this->string('search')->trim()->toString(),
            status: $this->string('status')->toString(),
            type: $this->string('type')->toString(),
        );
    }
}
