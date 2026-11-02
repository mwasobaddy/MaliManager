<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Platform audit-log filters, sort and focused row.
 *
 * `AuditService::filtersFromRequest()` takes these keys with `$request->only()`,
 * which silently drops anything malformed. Declaring them here means a bad
 * `date_from` or `direction` is reported instead of producing an unfiltered log
 * that looks like the filter did nothing.
 */
class AuditIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Read-only log view; the route is behind the platform admin middleware.
        return $this->user() !== null;
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
            'audit' => ['nullable', 'integer'],
        ];
    }
}
