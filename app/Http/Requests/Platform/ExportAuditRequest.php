<?php

namespace App\Http\Requests\Platform;

use App\Data\Audit\ExportAuditData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Platform audit-log CSV export.
 *
 * Same filter and sort parameters as the index, so the file matches the table.
 * The optional `tenant_id` narrows the platform log to one organization.
 */
class ExportAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exportAudit') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'actor' => ['nullable', 'string', 'max:191'],
            'event' => ['nullable', 'string', 'max:191'],
            'log_name' => ['nullable', 'string', 'max:191'],
            'subject_type' => ['nullable', 'string', 'max:191'],
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'tenant_id' => ['nullable', 'string', 'max:191'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    public function toData(): ExportAuditData
    {
        return new ExportAuditData(
            filters: $this->only([
                'actor', 'event', 'log_name', 'subject_type',
                'search', 'date_from', 'date_to', 'tenant_id',
            ]),
            sortColumn: $this->string('sort')->toString(),
            sortDirection: $this->string('direction')->toString(),
            tenantId: null,
        );
    }
}
