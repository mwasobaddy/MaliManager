<?php

declare(strict_types=1);

namespace App\Data\Assistant;

/**
 * Input for the AskAssistant action. Built from the validated request body so the
 * action never touches the HTTP layer.
 */
final readonly class AskAssistantData
{
    public function __construct(
        public string $question,
        public ?int $conversationId = null,
        public bool $newConversation = false,
        public ?string $title = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            question: (string) $validated['question'],
            conversationId: isset($validated['conversation_id']) ? (int) $validated['conversation_id'] : null,
            newConversation: (bool) ($validated['new_conversation'] ?? false),
            title: isset($validated['title']) ? (string) $validated['title'] : null,
        );
    }
}
