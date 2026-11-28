<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class AmendLeaseRentRequest extends FormRequest
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
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
            // Optional scheduled increment alongside the amendment: emptying
            // the percent clears the schedule, a value needs its date.
            'increment_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'increment_effective_date' => ['nullable', 'date', 'required_with:increment_percent'],
        ];
    }
}
