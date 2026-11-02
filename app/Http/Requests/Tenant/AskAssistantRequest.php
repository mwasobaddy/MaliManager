<?php

namespace App\Http\Requests\Tenant;

use App\Data\Assistant\AskAssistantData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guards POST /assistant/ask for the organization assistant.
 *
 * `conversation_id` and `new_conversation` arrive from a JSON body, so
 * `boolean` rather than `in:0,1` — a real JSON `false` would fail an `in` rule
 * that only ever sees the strings "0" and "1".
 *
 * The route already enforces `sub-permission:ai.use`; this only has to establish
 * a signed-in user, because the organization context is resolved in the
 * controller where a missing one returns the JSON error the page expects.
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
