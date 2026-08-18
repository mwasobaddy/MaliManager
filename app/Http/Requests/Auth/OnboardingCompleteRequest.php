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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, Rule|Password|string>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];

        if (! $this->ownsOrganization()) {
            $rules['account_type'] = ['required', Rule::in(['organization', 'occupant'])];

            if ($this->input('account_type') === 'organization') {
                $this->addOrganizationRules($rules);
            }
        } else {
            $this->addOrganizationRules($rules);
        }

        return $rules;
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
