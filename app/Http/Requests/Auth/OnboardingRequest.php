<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The onboarding landing page and the first-asset page.
 *
 * Both render the signed-in user's own onboarding state and take no input, so
 * there is nothing to validate. The route is behind `auth`, and every value the
 * page shows is resolved from that user — no id comes from the request.
 */
class OnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Own onboarding state only; nothing is keyed by a request parameter.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
