<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dashboard period filter.
 *
 * `year` and `month` are read with `$request->integer()`, which silently turns a
 * malformed value into 0. Declaring the range here means a bad link fails
 * loudly instead of quietly showing an empty chart.
 *
 * The route is behind `auth` and the service scopes every figure to the user's
 * accessible properties, so no per-record authorization applies.
 */
class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authenticated landing page; DashboardService scopes by access.
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
