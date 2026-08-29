<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\AiFeature;
use App\Exceptions\AssistantUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Support\Ai\AiGateway;
use App\Support\Ai\AssistantService;
use App\Support\Ai\Scope;
use App\Support\TenancyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organization ask-your-data assistant. Powered by AssistantService with an
 * org-scoped data boundary — the LLM can only read this organization's data.
 */
class AssistantController extends Controller
{
    public function __construct(
        private AssistantService $assistant,
        private AiGateway $gateway,
    ) {}

    public function page(Request $request): Response
    {
        $organization = TenancyContext::organization();
        $enabled = $organization !== null && $this->gateway->canUse($request->user(), AiFeature::AskData, $organization);

        return Inertia::render('tenant/assistant', [
            'enabled' => $enabled,
            'initial_messages' => $enabled ? $this->loadMessages($request->user(), Scope::org($organization->id)) : [],
            'quick_prompts' => [
                'How many units are vacant right now, by property?',
                'What rent is outstanding this month?',
                'Which leases expire in the next 60 days?',
                'Show my open maintenance backlog by priority.',
                'Summarise expenses by category this year.',
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

        $organization = TenancyContext::organization();

        if ($organization === null) {
            return response()->json(['error' => 'No organization context.'], 403);
        }

        $scope = Scope::org($organization->id);
        $conversation = AiConversation::resolve($request->user(), $scope, $validated['conversation_id'] ?? null, (bool) ($validated['new_conversation'] ?? false));

        $history = $this->historyFor($conversation);

        try {
            $result = $this->assistant->ask(
                $request->user(),
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
