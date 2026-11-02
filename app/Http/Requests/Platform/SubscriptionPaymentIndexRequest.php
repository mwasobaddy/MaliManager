<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Subscription payment filters, sort and focused row.
 *
 * `from`/`to` bound `received_on`; malformed dates previously reached
 * `whereDate` and returned an empty page rather than an error.
 */
class SubscriptionPaymentIndexRequest extends FormRequest
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
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'payment' => ['nullable', 'integer'],
        ];
    }
}
