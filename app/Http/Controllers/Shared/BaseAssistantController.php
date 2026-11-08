<?php

namespace App\Http\Controllers\Shared;

use App\Actions\Assistant\AskAssistant;
use App\Concerns\HandlesConversationHistory;
use App\Data\Assistant\AskAssistantData;
use App\Exceptions\AssistantUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Support\Ai\AiGateway;
use App\Support\Ai\Scope;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The one assistant, rendered three times.
 *
 * Platform, tenant and searcher assistants are the same feature — ask a
 * question within a data boundary, keep the conversation — differing only in
 * scope, view, prompts and route names. The bodies lived in each controller,
 * including a verbatim copy of loadMessages(), so a fix in one surface never
 * reached the other two.
 *
 * Concrete controllers stay because routes, middleware and Form Requests are
 * per-surface: each names its view, its prompts, how its scope resolves, and
 * what a missing scope answers. Everything shaped the same lives here.
 */
abstract class BaseAssistantController extends Controller
{
    use HandlesConversationHistory;

    public function __construct(
        protected AiGateway $gateway,
        protected AskAssistant $ask,
    ) {}

    /**
     * The scope this surface asks within, or null when the assistant is
     * unavailable here (no organization context, no linked person).
     */
    abstract protected function assistantScope(User $user): ?Scope;

    /**
     * Whether the assistant is offered here at all. Separate from the scope
     * because the two can disagree: the platform surface loads the latest
     * conversation under a fallback scope while still reporting disabled when
     * no usable credential exists.
     */
    abstract protected function assistantEnabled(User $user): bool;

    /**
     * The error a missing scope answers with. Per-surface because the fix
     * differs: a member needs an organization, an occupant needs a linked
     * account.
     */
    abstract protected function missingScopeError(): string;

    abstract protected function assistantView(): string;

    /**
     * @return array<int, string>
     */
    abstract protected function quickPrompts(): array;

    protected function renderAssistantPage(User $user): Response
    {
        $enabled = $this->assistantEnabled($user);
        $scope = $enabled ? $this->assistantScope($user) : null;

        return Inertia::render($this->assistantView(), [
            'enabled' => $enabled,
            'initial_messages' => $scope !== null ? $this->loadMessages($user, $scope) : [],
            'quick_prompts' => $this->quickPrompts(),
        ]);
    }

    protected function answerAssistant(User $user, AskAssistantData $data): JsonResponse
    {
        $scope = $this->assistantScope($user);

        if ($scope === null) {
            return response()->json(['error' => $this->missingScopeError()], 403);
        }

        try {
            $answer = $this->ask->handle($user, $scope, $data);
        } catch (AssistantUnavailableException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        return response()->json($answer->toArray());
    }

    /**
     * @return array<int, array{role: string, content: string, artifacts?: array}>
     */
    protected function loadMessages(User $user, Scope $scope): array
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
}
