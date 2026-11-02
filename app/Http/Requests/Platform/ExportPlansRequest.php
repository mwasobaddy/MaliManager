<?php

namespace App\Http\Requests\Platform;

use App\Data\Plans\ExportPlansData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Plan CSV export.
 *
 * The search and status filters must match the index, or "Export all" would
 * disagree with the table it was clicked from.
 */
class ExportPlansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exportPlans') ?? false;
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

    public function toData(): ExportPlansData
    {
        return new ExportPlansData(
            scope: $this->input('scope') === 'page' ? 'page' : 'all',
            page: max(1, (int) $this->integer('page', 1)),
            search: $this->string('search')->trim()->toString(),
            status: $this->string('status')->toString(),
        );
    }
}
