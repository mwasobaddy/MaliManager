<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Organization audit-log filters.
 *
 * `AuditService::filtersFromRequest()` reads these keys with `$request->only()`.
 * Declaring them means a malformed date is reported instead of silently
 * returning an unfiltered log.
 */
class AuditIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Read-only log; the route is behind the organization member group.
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
        ];
    }
}
