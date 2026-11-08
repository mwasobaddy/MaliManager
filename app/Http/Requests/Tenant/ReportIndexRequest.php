<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The report picker page.
 *
 * Read-only and parameter-free: it renders the report types, while the
 * data/csv/print endpoints take their own `ReportRequest`. The module-access
 * rule lives in the controller's shared guard alongside those endpoints.
 */
class ReportIndexRequest extends FormRequest
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
        return [];
    }
}
