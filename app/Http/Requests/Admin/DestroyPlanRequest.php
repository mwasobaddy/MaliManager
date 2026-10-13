<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DestroyPlanRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Authorization is handled by the `can:deletePlans` route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Deleting a pricing tier changes what an organization can be sold, so it
     * asks for the password like the other destructive actions in the app.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
