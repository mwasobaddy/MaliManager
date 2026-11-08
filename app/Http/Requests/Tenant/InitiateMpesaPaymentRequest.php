<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class InitiateMpesaPaymentRequest extends FormRequest
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
            // Kenyan mobile in any common spelling: 07…, 01…, 254…,
            // +254…. Normalized to 254… in InitiateMpesaPayment.
            'phone' => ['required', 'string', 'regex:/^(\+254|254|0)([17][0-9]{8})$/'],
        ];
    }
}
