<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanStatusRequest extends FormRequest
{
    /**
     * Authorization is handled by the `can:editPlans` route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A boolean rather than an enum: `plans.is_active` is a plain column, and
     * the two states it can hold are exactly "available" and "not".
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
