<?php

namespace App\Http\Requests\Platform;

use App\Data\SubscriptionPayments\ExportSubscriptionPaymentsData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Subscription-payment CSV export.
 *
 * The search and date range must match the index so "Export all" cannot
 * disagree with the table it was clicked from.
 */
class ExportSubscriptionPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exportSubscriptionPayments') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['nullable', Rule::in(['all', 'page'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    public function toData(): ExportSubscriptionPaymentsData
    {
        return new ExportSubscriptionPaymentsData(
            scope: $this->input('scope') === 'page' ? 'page' : 'all',
            page: max(1, (int) $this->integer('page', 1)),
            search: $this->string('search')->trim()->toString(),
            from: $this->string('from')->toString(),
            to: $this->string('to')->toString(),
        );
    }
}
