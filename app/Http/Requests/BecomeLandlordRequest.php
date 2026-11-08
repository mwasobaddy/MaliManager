<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Becoming a landlord: the three things signup deliberately did not ask.
 *
 * Everything else — name, phone, password — is already known from onboarding.
 * Plan choice lives here rather than at signup so it meets the user at the
 * moment of intent, next to what the plan buys them.
 */
class BecomeLandlordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Self-service upgrade; the org does not exist yet to authorize against.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'plan_slug' => ['required', 'string', 'exists:plans,slug'],
            'list_publicly' => ['nullable', 'boolean'],
        ];
    }
}
