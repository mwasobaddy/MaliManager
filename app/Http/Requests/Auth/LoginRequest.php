<?php

namespace App\Http\Requests\Auth;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The identifier is an email address or a phone number: tenants created
 * without an email sign in with the number they were onboarded with.
 */
class LoginRequest extends FormRequest
{
    /**
     * Public by design: login is how a session is established, so there is
     * no authenticated user to authorize yet. The one-time code is the gate.
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
            'identifier' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $identifier = is_string($this->input('identifier')) ? $this->input('identifier') : '';

            if (filter_var(strtolower($identifier), FILTER_VALIDATE_EMAIL) === false && ! PhoneNumber::isPhone($identifier)) {
                $validator->errors()->add('identifier', 'Enter a valid email address or phone number.');
            }
        });
    }
}
