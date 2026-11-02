<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The organization assistant page and its conversation list.
 *
 * Read-only: both render the signed-in member's own conversations within the
 * current organization, scoped by `HandlesConversationHistory`.
 */
class AssistantPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Read-only own-conversation views; the trait scopes every query.
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
