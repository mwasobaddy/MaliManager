<?php

namespace App\Http\Controllers;

use App\Enums\AiFeature;
use App\Exceptions\AssistantUnavailableException;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Support\Ai\AiGateway;
use App\Support\Ai\AssistantService;
use App\Support\Ai\Scope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Occupant-facing AI assistant. Strictly scoped to the signed-in user's OWN
 * data (leases + maintenance requests) via a person-scoped data boundary.
 */
class SearcherAssistantController extends Controller
{
    public function __construct(
        private AssistantService $assistant,
        private AiGateway $gateway,
    ) {}

    public function page(Request $request): Response
    {
        $user = $request->user();
        $enabled = $user->person_id !== null && $this->gateway->canUse($user, AiFeature::AskData);

        return Inertia::render('searcher/assistant', [
            'enabled' => $enabled,
            'initial_messages' => $enabled ? $this->loadMessages($user, Scope::person($user->person_id, $user->id)) : [],
            'quick_prompts' => [
                'What is my current rent and when is it due?',
                'Show my active lease details.',
                'What maintenance requests have I raised, and their status?',
                'Do I have any unresolved maintenance issues?',
            ],
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'integer'],
            'new_conversation' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        if ($user->person_id === null) {
            return response()->json(['error' => 'AI is not configured for your account.'], 403);
        }

        $scope = Scope::person($user->person_id, $user->id);
        $conversation = AiConversation::resolve($user, $scope, $validated['conversation_id'] ?? null, (bool) ($validated['new_conversation'] ?? false));

        $history = $this->historyFor($conversation);

        try {
            $result = $this->assistant->ask(
                $user,
                $scope,
                $validated['question'],
                $history,
            );
        } catch (AssistantUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        $conversation->messages()->create(['role' => 'user', 'content' => $validated['question']]);
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['answer'],
            'artifacts' => $result['artifacts'] ?? [],
        ]);
        $conversation->touch('last_message_at');

        return response()->json([
            'answer' => $result['answer'],
            'artifacts' => $result['artifacts'] ?? [],
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * @return array<int, array{role: string, content: string, artifacts?: array}>
     */
    private function loadMessages(User $user, Scope $scope): array
    {
        $conversation = AiConversation::findLatest($user, $scope);

        if ($conversation === null) {
            return [];
        }

        return $conversation->messages()
            ->orderBy('id')
            ->get(['role', 'content', 'artifacts'])
            ->map(fn (AiMessage $message) => [
                'role' => $message->role,
                'content' => $message->content,
                'artifacts' => $message->artifacts ?? [],
            ])
            ->all();
    }

    /**
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
