<?php

namespace App\Concerns;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Support\Ai\Scope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Shared logic for listing, showing, and deleting AI conversations.
 * Used by Platform, Tenant, and Searcher assistant controllers.
 */
trait HandlesConversationHistory
{
    /**
     * Render the conversation history page.
     */
    public function history(Request $request): InertiaResponse
    {
        $user = $request->user();
        $scope = $this->scopeForHistory($request);
        $conversations = $this->listConversations($user, $scope);

        return Inertia::render($this->historyView(), [
            'conversations' => $conversations,
            'scope' => $scope->type,
        ]);
    }

    /**
     * Show a single conversation with its messages.
     */
    public function showConversation(Request $request, int $id): InertiaResponse|JsonResponse
    {
        $user = $request->user();
        $scope = $this->scopeForHistory($request);

        $conversation = AiConversation::where('user_id', $user->id)
            ->where('scope', $scope->type)
            ->when($scope->type !== Scope::PLATFORM, fn ($q) => $q->where('organization_id', $scope->organizationId))
            ->with(['messages' => fn ($q) => $q->orderBy('id')])
            ->find($id);

        if ($conversation === null) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Conversation not found.'], 404);
            }

            return redirect()->route($this->historyRoute());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $conversation->id,
                'title' => $conversation->title,
                'created_at' => $conversation->created_at->toISOString(),
                'messages' => $conversation->messages->map(fn (AiMessage $message) => [
                    'role' => $message->role,
                    'content' => $message->content,
                    'artifacts' => $message->artifacts ?? [],
                ])->all(),
            ]);
        }

        return Inertia::render($this->conversationView(), [
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'created_at' => $conversation->created_at->toISOString(),
            ],
            'messages' => $conversation->messages->map(fn (AiMessage $message) => [
                'role' => $message->role,
                'content' => $message->content,
                'artifacts' => $message->artifacts ?? [],
            ])->all(),
            'askUrl' => $this->askRouteUrl($request),
            'scope' => $scope->type,
        ]);
    }

    /**
     * Delete a conversation and its messages.
     */
    public function destroyConversation(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $scope = $this->scopeForHistory($request);

        $deleted = AiConversation::where('user_id', $user->id)
            ->where('scope', $scope->type)
            ->when($scope->type !== Scope::PLATFORM, fn ($q) => $q->where('organization_id', $scope->organizationId))
            ->find($id);

        if ($deleted === null) {
            return response()->json(['error' => 'Conversation not found.'], 404);
        }

        $deleted->messages()->delete();
        $deleted->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * List conversations for the user within the given scope, ordered by
     * most recently active. Includes a preview of the first user message.
     *
     * @return array<int, array{id: int, title: string|null, created_at: string, last_message_at: string, message_count: int, preview: string|null}>
     */
    private function listConversations(User $user, Scope $scope): array
    {
        $conversations = AiConversation::where('user_id', $user->id)
            ->where('scope', $scope->type)
            ->when($scope->type !== Scope::PLATFORM, fn ($q) => $q->where('organization_id', $scope->organizationId))
            ->withCount('messages')
            ->latest('last_message_at')
            ->get();

        // Fetch first user message for each conversation (preview).
        $conversationIds = $conversations->pluck('id')->all();
        $previews = [];

        if ($conversationIds !== []) {
            $firstMessages = AiMessage::whereIn('ai_conversation_id', $conversationIds)
                ->where('role', 'user')
                ->select('ai_conversation_id', 'content')
                ->selectRaw('MIN(id) as first_id')
                ->groupBy('ai_conversation_id')
                ->get();

            foreach ($firstMessages as $message) {
                $previews[$message->ai_conversation_id] = mb_substr($message->content, 0, 120);
            }
        }

        return $conversations->map(fn (AiConversation $conversation) => [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'created_at' => $conversation->created_at->toISOString(),
            'last_message_at' => $conversation->last_message_at->toISOString(),
            'message_count' => $conversation->messages_count,
            'preview' => $previews[$conversation->id] ?? null,
        ])->all();
    }

    /**
     * Resolve the scope for history queries. Override in controllers
     * where scope resolution differs (e.g., platform uses gateway).
     */
    abstract protected function scopeForHistory(Request $request): Scope;

    /**
     * The Inertia component name for the history list page.
     */
    abstract protected function historyView(): string;

    /**
     * The Inertia component name for a single conversation page.
     */
    abstract protected function conversationView(): string;

    /**
     * The named route for redirecting after history actions.
     */
    abstract protected function historyRoute(): string;

    /**
     * Build the ask API URL for resuming a conversation.
     */
    abstract protected function askRouteUrl(Request $request): string;
}
