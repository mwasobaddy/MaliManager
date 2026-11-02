<?php

declare(strict_types=1);

namespace App\Data\Assistant;

/**
 * Output of the AskAssistant action. Controllers map this to a JSON response, so
 * the action itself never builds one.
 */
final readonly class AssistantAnswerData
{
    /**
     * @param  array<int, mixed>  $artifacts
     */
    public function __construct(
        public string $answer,
        public array $artifacts,
        public int $conversationId,
    ) {}

    /**
     * @return array{answer: string, artifacts: array<int, mixed>, conversation_id: int}
     */
    public function toArray(): array
    {
        return [
            'answer' => $this->answer,
            'artifacts' => $this->artifacts,
            'conversation_id' => $this->conversationId,
        ];
    }
}
