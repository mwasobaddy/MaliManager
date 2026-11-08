<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The profile edit screen.
 *
 * Read-only and parameter-free: it renders the caller's own profile state.
 * Self-service by construction — no other account can be named.
 */
class ProfileEditRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Own profile screen; nothing is keyed by a request parameter.
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
