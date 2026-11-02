<?php

declare(strict_types=1);

namespace App\Actions\Assistant;

use App\Data\Assistant\AskAssistantData;
use App\Data\Assistant\AssistantAnswerData;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Service;
use App\Support\Ai\AssistantService;
use App\Support\Ai\Scope;

/**
 * One question to the AI assistant, for any scope: platform, organization or
 * person. The caller resolves the Scope; this action owns the conversation
 * lifecycle so the three entry points cannot drift apart.
 *
 * AssistantUnavailableException is intentionally not caught here — mapping it to
 * a 403 is an HTTP concern and stays in the controller.
 */
class AskAssistant extends Service
{
    public function __construct(private AssistantService $assistant) {}

    public function handle(User $user, Scope $scope, AskAssistantData $data): AssistantAnswerData
    {
        $conversation = AiConversation::resolve(
            $user,
            $scope,
            $data->conversationId,
            $data->newConversation,
        );

        if ($conversation->wasRecentlyCreated && ! empty($data->title)) {
            $conversation->update(['title' => $data->title]);
        }

        $result = $this->assistant->ask(
            $user,
            $scope,
            $data->question,
            $this->historyFor($conversation),
        );

        $conversation->messages()->create(['role' => 'user', 'content' => $data->question]);
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['answer'],
            'artifacts' => $result['artifacts'] ?? [],
        ]);
        $conversation->touch('last_message_at');

        return new AssistantAnswerData(
            answer: (string) $result['answer'],
            artifacts: $result['artifacts'] ?? [],
            conversationId: (int) $conversation->id,
        );
    }

    /**
     * The last six turns, oldest first, for model context.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function historyFor(AiConversation $conversation): array
    {
        return $conversation->messages()
            ->orderBy('id')
            ->limit(6)
            ->get(['role', 'content'])
            ->map(fn (AiMessage $message) => [
                'role' => $message->role === 'error' ? 'assistant' : $message->role,
                'content' => $message->content,
            ])
            ->all();
    }
}
