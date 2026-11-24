<?php

namespace App\Http\Requests\Tenant;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveInOccupantRequest extends FormRequest
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
            'move_in_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', Rule::in(Payment::METHODS)],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
