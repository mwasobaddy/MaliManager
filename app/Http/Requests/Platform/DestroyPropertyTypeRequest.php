<?php

namespace App\Http\Requests\Platform;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting one entry from the central property-type catalog.
 *
 * The catalog is platform-managed and every tenant property form reads it, so
 * the permission is the platform `deletePropertyTypes` gate rather than an
 * ownership check on the row. The route applies the same gate; asserting it
 * here keeps the rule with the action.
 *
 * The password confirms the actor, not the authority — the permission already
 * decided that. A catalog entry silently removed is hard to notice and cannot be
 * undone from the interface, which is why it gets the same gate as a staff
 * removal.
 */
class DestroyPropertyTypeRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('deletePropertyTypes') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
