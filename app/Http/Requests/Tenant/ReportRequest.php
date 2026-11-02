<?php

namespace App\Http\Requests\Tenant;

use App\Services\Reporting\ReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards the report data endpoints: `/reports/data`, `/reports/csv` and
 * `/reports/print`.
 *
 * Not used by `/reports` itself, which renders the picker and takes no range —
 * requiring `type` there would 422 the page a user opens first.
 *
 * Module access depends on the organization and the caller's sub-role, which
 * the controller's existing guard already resolves; this only establishes a
 * signed-in user so the two checks do not drift apart.
 */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(ReportService::TYPES)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
