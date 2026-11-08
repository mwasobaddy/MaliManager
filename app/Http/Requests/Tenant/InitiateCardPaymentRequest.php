<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Starting a card checkout takes no input: the organization, plan and
 * amount all come from the tenant context. The class exists so the
 * write endpoint is never on a bare Request.
 */
class InitiateCardPaymentRequest extends FormRequest
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
        return [];
    }
}
