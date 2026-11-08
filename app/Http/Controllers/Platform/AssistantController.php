<?php

namespace App\Http\Controllers\Platform;

use App\Enums\AiFeature;
use App\Http\Controllers\Shared\BaseAssistantController;
use App\Http\Requests\Platform\AskAssistantRequest;
use App\Http\Requests\Platform\AssistantConversationRequest;
use App\Http\Requests\Platform\AssistantPageRequest;
use App\Models\Organization;
use App\Models\User;
use App\Support\Ai\Scope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Platform-wide admin assistant. Uses a platform scope (no forced tenant
 * filter) so administrators can analyse data across every organization.
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
        return $this->gateway->resolvePlatformOrFirstOrg($user, AiFeature::AskData) !== null;
    }

    /**
     * Platform-wide scope when a platform credential exists, otherwise the
     * first organization the user can access that has a usable credential
     * (so the assistant still works from the central dashboard).
     */
    protected function assistantScope(User $user): ?Scope
    {
        $credential = $this->gateway->resolvePlatformOrFirstOrg($user, AiFeature::AskData);

        if ($credential === null) {
            return Scope::platform();
        }

        // Platform-wide analysis when powered by a platform or personal key;
        // only narrow to a single tenant when the resolved credential is an
        // organization's own shared key.
        if ($credential->setting->owner_type === 'platform') {
            return Scope::platform();
        }

        if ($credential->setting->owner_type === (new Organization)->getMorphClass()) {
            return Scope::org($credential->setting->owner_id);
        }

        return Scope::platform();
    }

    protected function missingScopeError(): string
    {
        return 'AI is not configured for your account.';
    }

    protected function assistantView(): string
    {
        return 'platform/assistant/index';
    }

    /**
     * @return array<int, string>
     */
    protected function quickPrompts(): array
    {
        return [
            'How many organizations are active on the platform?',
            'What is the total occupied vs vacant unit ratio across all orgs?',
            'Which organizations have the most open maintenance requests?',
            'Total rent potential collected vs outstanding, platform-wide.',
            'Leases expiring in the next 60 days across all organizations.',
        ];
    }

    protected function scopeForHistory(Request $request): Scope
    {
        return $this->assistantScope($request->user()) ?? Scope::platform();
    }

    protected function historyView(): string
    {
        return 'platform/assistant/history';
    }

    protected function conversationView(): string
    {
        return 'platform/assistant/conversation';
    }

    protected function historyRoute(): string
    {
        return 'platform.assistant.history';
    }

    protected function askRouteUrl(Request $request): string
    {
        return route('platform.assistant.ask');
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
