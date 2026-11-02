<?php

namespace App\Http\Requests\Platform;

use App\Models\AiConversation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Viewing or deleting one assistant conversation.
 *
 * The route id is not a bound model, so ownership is asserted here rather than
 * assumed: a conversation that belongs to another user is refused before the
 * action runs. `HandlesConversationHistory` still filters by scope as well, so
 * the platform view cannot reach an organization conversation.
 */
class AssistantConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AiConversation::query()
            ->whereKey($this->route('id'))
            ->where('user_id', $this->user()?->id)
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
