<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The one-time-code screen and its resend.
 *
 * Both endpoints are pre-authentication and take no input: they read the email
 * the login step parked in the session, and redirect back to login when it is
 * absent. There is nothing to validate, and authorizing is the session check
 * itself.
 */
class OtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Pre-authentication by design: the pending email lives in the session.
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
