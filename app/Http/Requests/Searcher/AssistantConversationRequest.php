<?php

namespace App\Http\Requests\Searcher;

use App\Models\AiConversation;
use App\Support\Ai\Scope;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Viewing or deleting one occupant-assistant conversation.
 *
 * The conversation must belong to the caller and be in person scope. Checking
 * the scope as well as the owner stops a platform or organization conversation
 * from being read through an occupant URL.
 */
class AssistantConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AiConversation::query()
            ->whereKey($this->route('id'))
            ->where('user_id', $this->user()?->id)
            ->where('scope', Scope::PERSON)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
