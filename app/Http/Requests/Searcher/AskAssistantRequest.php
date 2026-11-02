<?php

namespace App\Http\Requests\Searcher;

use App\Data\Assistant\AskAssistantData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guards POST searcher/assistant/ask.
 *
 * The rules match the other two assistants because the payload is the same
 * shape. Authorization stays a signed-in check: the occupant-specific
 * `person_id` guard is in the controller, where it returns the JSON error the
 * page reads instead of a bare 403.
 */
class AskAssistantRequest extends FormRequest
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
            'question' => ['required', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'integer'],
            'new_conversation' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): AskAssistantData
    {
        return AskAssistantData::fromValidated($this->validated());
    }
}
