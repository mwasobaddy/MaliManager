<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Platform expense list filters, sort and focused row.
 *
 * `from`/`to` bound `spent_on`; malformed dates used to pass straight into a
 * `whereDate` and quietly return nothing.
 */
class PlatformExpenseIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Read-only list; the route is behind the platform admin middleware.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'expense' => ['nullable', 'integer'],
        ];
    }
}
