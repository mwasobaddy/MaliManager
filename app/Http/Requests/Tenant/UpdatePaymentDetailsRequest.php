<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The landlord's tenant-facing payment instructions. Everything optional:
 * an owner may publish only a till number, only notes, or nothing yet.
 */
class UpdatePaymentDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paybill_number' => ['nullable', 'string', 'max:20'],
            'account_reference' => ['nullable', 'string', 'max:50'],
            'till_number' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
