<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The personal AI settings screen and its key removal.
 *
 * Both resolve the setting from `$this->user()`, so no record id arrives in the
 * request and there is nothing to validate. Ownership is structural: a user can
 * only ever read or delete their own credential.
 */
class PersonalAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The row is looked up by the authenticated user; no id is trusted.
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
