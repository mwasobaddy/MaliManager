<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards POST /drafting/generate.
 *
 * The route enforces `sub-permission:ai.use`, so authorization here only has to
 * establish a signed-in user; whether the organization has a usable AI
 * credential is decided after validation, where the failure can be reported as
 * a 502 rather than a 403.
 */
class GenerateDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['rent_reminder', 'lease_expiry_notice', 'move_out_letter', 'listing_description'])],
            'tone' => ['nullable', Rule::in(['formal', 'friendly'])],
            'fields' => ['required', 'array'],
            'fields.*' => ['nullable', 'string', 'max:500'],
        ];
    }
}
