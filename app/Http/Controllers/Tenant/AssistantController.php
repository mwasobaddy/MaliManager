<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Assistant\AskAssistant;
use App\Concerns\HandlesConversationHistory;
use App\Enums\AiFeature;
use App\Exceptions\AssistantUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AskAssistantRequest;
use App\Http\Requests\Tenant\AssistantConversationRequest;
use App\Http\Requests\Tenant\AssistantPageRequest;
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
    use HandlesConversationHistory;

    public function __construct(
        private AssistantService $assistant,
        private AiGateway $gateway,
    ) {}

    public function page(AssistantPageRequest $request): Response
    {
        $organization = TenancyContext::organization();
        $enabled = $organization !== null && $this->gateway->canUse($request->user(), AiFeature::AskData, $organization);

        return Inertia::render('organisation/assistant', [
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

    public function ask(AskAssistantRequest $request, AskAssistant $action): JsonResponse
    {
        $organization = TenancyContext::organization();

        if ($organization === null) {
            return response()->json(['error' => 'No organization context.'], 403);
        }

        try {
            $answer = $action->handle($request->user(), Scope::org($organization->id), $request->toData());
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
        $organization = TenancyContext::organization();

        return Scope::org($organization?->id ?? 0);
    }

    protected function historyView(): string
    {
        return 'tenant/assistant/history';
    }

    protected function conversationView(): string
    {
        return 'tenant/assistant/conversation';
    }

    protected function historyRoute(): string
    {
        return 'tenant.assistant.history';
    }

    protected function askRouteUrl(Request $request): string
    {
        return route('tenant.assistant.ask');
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
