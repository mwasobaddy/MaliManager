<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ReactivationRequest extends FormRequest
{
    /**
     * Public by design: the requester is logged out by definition.
     * Throttled at the route so an address cannot be probed cheaply.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ];
    }
}
