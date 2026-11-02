<?php

namespace App\Http\Requests\Searcher;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The occupant assistant page and its conversation list.
 *
 * Read-only: both render the signed-in occupant's own conversations, scoped by
 * `HandlesConversationHistory` to person scope.
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
