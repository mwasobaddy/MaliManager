<?php

namespace App\Http\Requests\Platform;

use App\Data\SubscriptionPayments\ImportSubscriptionPaymentsData;
use Illuminate\Foundation\Http\FormRequest;

class ImportSubscriptionPaymentRequest extends FormRequest
{
    /**
     * Authorization is handled by the `can:importSubscriptionPayments` route middleware.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('importSubscriptionPayments') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // `text/plain` covers .csv on most uploads.
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Upload a CSV file (a .txt file containing CSV also works).',
            'file.max' => 'The import file may not be larger than 2 MB.',
        ];
    }

    public function toData(): ImportSubscriptionPaymentsData
    {
        return new ImportSubscriptionPaymentsData(
            file: $this->file('file'),
            actor: $this->user(),
        );
    }
}
