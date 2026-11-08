<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Expense list filter.
 *
 * Read-only: `category` narrows the organization's own expenses. Authorization
 * is membership, asserted by the surrounding route group.
 */
class ExpenseIndexRequest extends FormRequest
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
            'category' => ['nullable', 'string', 'max:64'],
        ];
    }
}
