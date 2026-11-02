<?php

namespace App\Http\Requests\Platform;

use App\Data\Users\ExportUsersData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * User CSV export.
 *
 * The search, status and role filters must match the index so "Export all"
 * cannot disagree with the table it was clicked from.
 */
class ExportUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exportUsers') ?? false;
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
            'role' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function toData(): ExportUsersData
    {
        return new ExportUsersData(
            scope: $this->input('scope') === 'page' ? 'page' : 'all',
            page: max(1, (int) $this->integer('page', 1)),
            search: $this->string('search')->trim()->toString(),
            status: $this->string('status')->toString(),
            role: $this->string('role')->toString(),
        );
    }
}
