<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Guards POST platform/assistant/ask.
 *
 * `conversation_id` and `new_conversation` are both nullable booleans/ids that
 * arrive from a JSON body, so `boolean` rather than `in:0,1` — a real JSON
 * `false` would fail an `in` rule that only ever sees the strings "0" and "1".
 */
class AskAssistantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
}
