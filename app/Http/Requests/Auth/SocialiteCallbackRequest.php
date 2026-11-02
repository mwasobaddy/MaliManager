<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * OAuth callback.
 *
 * Socialite reads `code` and `state` straight off the request, and the provider
 * decides whether they are valid — a `rules()` entry here would duplicate that
 * check badly. The endpoint is pre-authentication, so authorizing is the
 * provider's signature check inside the driver.
 */
class SocialiteCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public OAuth callback; the provider verifies the exchange.
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
