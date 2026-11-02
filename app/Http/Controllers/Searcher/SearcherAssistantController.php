<?php

namespace App\Http\Controllers\Searcher;

use App\Actions\Assistant\AskAssistant;
use App\Concerns\HandlesConversationHistory;
use App\Enums\AiFeature;
use App\Exceptions\AssistantUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Searcher\AskAssistantRequest;
use App\Http\Requests\Searcher\AssistantConversationRequest;
use App\Http\Requests\Searcher\AssistantPageRequest;
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
    use HandlesConversationHistory;

    public function __construct(
        private AssistantService $assistant,
        private AiGateway $gateway,
    ) {}

    public function page(AssistantPageRequest $request): Response
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

    public function ask(AskAssistantRequest $request, AskAssistant $action): JsonResponse
    {
        $user = $request->user();

        if ($user->person_id === null) {
            return response()->json(['error' => 'AI is not configured for your account.'], 403);
        }

        try {
            $answer = $action->handle($user, Scope::person($user->person_id, $user->id), $request->toData());
        } catch (AssistantUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        return response()->json($answer->toArray());
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
    protected function scopeForHistory(Request $request): Scope
    {
        $user = $request->user();

        return Scope::person($user->person_id, $user->id);
    }

    protected function historyView(): string
    {
        return 'searcher/assistant/history';
    }

    protected function conversationView(): string
    {
        return 'searcher/assistant/conversation';
    }

    protected function historyRoute(): string
    {
        return 'searcher.assistant.history';
    }

    protected function askRouteUrl(Request $request): string
    {
        return route('searcher.assistant.ask');
    }

    // Route targets; the bodies live in HandlesConversationHistory.
    public function history(AssistantPageRequest $request): Response
    {
        return $this->handleHistory($request);
    }

    public function showConversation(AssistantConversationRequest $request, int $id): Response|JsonResponse
    {
        return $this->handleConversation($request, $id);
    }

    public function destroyConversation(AssistantConversationRequest $request, int $id): JsonResponse
    {
        return $this->handleDeleteConversation($request, $id);
    }
}
