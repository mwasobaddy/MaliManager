<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The platform assistant page and its conversation list.
 *
 * Read-only: both render the signed-in admin's own conversations, scoped by
 * `HandlesConversationHistory`, and take no input.
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
