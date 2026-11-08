<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Organization dashboard period filter.
 *
 * Read-only: `year` and `month` pick the reporting window for the caller's own
 * organization. A malformed value is reported rather than silently coerced to
 * an empty chart.
 */
class OrganizationDashboardRequest extends FormRequest
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
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ];
    }
}
