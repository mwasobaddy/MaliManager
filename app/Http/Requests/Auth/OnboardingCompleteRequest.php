<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class OnboardingCompleteRequest extends FormRequest
{
    /**
     * Determine if the user already owns an organization.
     */
    protected function ownsOrganization(): bool
    {
        return $this->user()->organizations()->wherePivot('is_owner', true)->exists();
    }

    /**
     * Self-service: an authenticated user completes their own onboarding. Every
     * rule reads `$this->user()`; no account id arrives from the request, so
     * there is no other record to authorize against.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Guest-minimal: the profile every user needs, landlord or tenant. The
     * organization branch used to live here; it relocated to
     * `BecomeLandlordRequest`, which runs when the user chooses to upgrade.
     *
     * @return array<string, array<int, Rule|Password|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * Add the organization creation/update rules to the given rules array.
     *
     * @param  array<string, array<int, Rule|Password|string>>  $rules
     */
    protected function addOrganizationRules(array &$rules): void
    {
        $rules['organization_name'] = ['required', 'string', 'max:255'];
        $rules['currency'] = ['required', 'string', 'size:3'];
        $rules['plan_slug'] = ['required', 'string', 'exists:plans,slug'];
    }
}
