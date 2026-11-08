<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payment list filter.
 *
 * Read-only: `status` narrows the organization's own payments. Authorization
 * is membership, asserted by the surrounding route group.
 */
class PaymentIndexRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'max:64'],
        ];
    }
}
