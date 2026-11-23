<?php

namespace App\Http\Requests\Trash;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk trash mutation: module plus row ids, verified by password.
 *
 * The route middleware owns authorization (tenant delete gates filter
 * the modules, platform delete gates filter theirs); the per-row tier
 * check happens in the action, so a forged id only yields a "not
 * found in your trash" skip, never another actor's row.
 */
class TrashBulkRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'module' => ['required', 'string', 'max:64'],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'min:1'],
            'password' => $this->currentPasswordRules(),
        ];
    }
}
