<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\AiFeature;
use App\Http\Controllers\Shared\BaseAssistantController;
use App\Http\Requests\Tenant\AskAssistantRequest;
use App\Http\Requests\Tenant\AssistantConversationRequest;
use App\Http\Requests\Tenant\AssistantPageRequest;
use App\Models\User;
use App\Support\Ai\Scope;
use App\Support\TenancyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Organization ask-your-data assistant. Powered by AssistantService with an
 * org-scoped data boundary — the LLM can only read this organization's data.
 */
class AssistantController extends BaseAssistantController
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
        $organization = TenancyContext::organization();

        return $organization !== null
            && $this->gateway->canUse($user, AiFeature::AskData, $organization);
    }

    protected function assistantScope(User $user): ?Scope
    {
        $organization = TenancyContext::organization();

        return $organization === null ? null : Scope::org($organization->id);
    }

    protected function missingScopeError(): string
    {
        return 'No organization context.';
    }

    protected function assistantView(): string
    {
        return 'organisation/assistant';
    }

    /**
     * @return array<int, string>
     */
    protected function quickPrompts(): array
    {
        return [
            'How many units are vacant right now, by property?',
            'What rent is outstanding this month?',
            'Which leases expire in the next 60 days?',
            'Show my open maintenance backlog by priority.',
            'Summarise expenses by category this year.',
        ];
    }

    protected function scopeForHistory(Request $request): Scope
    {
        return $this->assistantScope($request->user()) ?? Scope::org(0);
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
