<?php

namespace App\Http\Controllers\Searcher;

use App\Enums\AiFeature;
use App\Http\Controllers\Shared\BaseAssistantController;
use App\Http\Requests\Searcher\AskAssistantRequest;
use App\Http\Requests\Searcher\AssistantConversationRequest;
use App\Http\Requests\Searcher\AssistantPageRequest;
use App\Models\User;
use App\Support\Ai\Scope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Occupant-facing AI assistant. Strictly scoped to the signed-in user's OWN
 * data (leases + maintenance requests) via a person-scoped data boundary.
 */
class SearcherAssistantController extends BaseAssistantController
{
    public function page(AssistantPageRequest $request): Response
    {
        return $this->renderAssistantPage($request->user());
    }

    public function ask(AskAssistantRequest $request): JsonResponse
    {
        return $this->answerAssistant($request->user(), $request->toData());
    }

    protected function assistantEnabled(User $user): bool
    {
        return $user->person_id !== null
            && $this->gateway->canUse($user, AiFeature::AskData);
    }

    protected function assistantScope(User $user): ?Scope
    {
        return $user->person_id === null
            ? null
            : Scope::person($user->person_id, $user->id);
    }

    protected function missingScopeError(): string
    {
        return 'AI is not configured for your account.';
    }

    protected function assistantView(): string
    {
        return 'searcher/assistant';
    }

    /**
     * @return array<int, string>
     */
    protected function quickPrompts(): array
    {
        return [
            'What is my current rent and when is it due?',
            'Show my active lease details.',
            'What maintenance requests have I raised, and their status?',
            'Do I have any unresolved maintenance issues?',
        ];
    }

    protected function scopeForHistory(Request $request): Scope
    {
        return $this->assistantScope($request->user()) ?? Scope::person(0, $request->user()->id);
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
