<?php

namespace App\Http\Requests\Trash;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Empties one trash module in scope, verified by password.
 */
class TrashEmptyRequest extends FormRequest
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
            'password' => $this->currentPasswordRules(),
        ];
    }
}
