<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The drafting studio page.
 *
 * Read-only and parameter-free: it renders whether the organization has a usable
 * AI credential. The route already enforces `ai.use`, so authorizing only has to
 * establish a signed-in user.
 */
class DraftingPageRequest extends FormRequest
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
        return [];
    }
}
