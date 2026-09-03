<?php

namespace App\Http\Controllers\Platform;

use App\Concerns\HandlesConversationHistory;
use App\Enums\AiFeature;
use App\Exceptions\AssistantUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Organization;
use App\Models\User;
use App\Support\Ai\AiGateway;
use App\Support\Ai\AssistantService;
use App\Support\Ai\Scope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform-wide admin assistant. Uses a platform scope (no forced tenant
 * filter) so administrators can analyse data across every organization.
 */
class AssistantController extends Controller
{
    use HandlesConversationHistory;

    public function __construct(
        private AssistantService $assistant,
        private AiGateway $gateway,
    ) {}

    public function page(Request $request): Response
    {
        $user = $request->user();
        $enabled = $this->gateway->resolvePlatformOrFirstOrg($user, AiFeature::AskData) !== null;

        return Inertia::render('platform/assistant/index', [
            'enabled' => $enabled,
            'initial_messages' => $enabled ? $this->loadMessages($user, $this->scopeFor($user)) : [],
            'quick_prompts' => [
                'How many organizations are active on the platform?',
                'What is the total occupied vs vacant unit ratio across all orgs?',
                'Which organizations have the most open maintenance requests?',
                'Total rent potential collected vs outstanding, platform-wide.',
                'Leases expiring in the next 60 days across all organizations.',
            ],
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'integer'],
            'new_conversation' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $scope = $this->scopeFor($user);
        $conversation = AiConversation::resolve($user, $scope, $validated['conversation_id'] ?? null, (bool) ($validated['new_conversation'] ?? false));

        // Set title on new conversations.
        if ($conversation->wasRecentlyCreated && ! empty($validated['title'])) {
            $conversation->update(['title' => $validated['title']]);
        }

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
     * Platform-wide scope when a platform credential exists, otherwise the
     * first organization the user can access that has a usable credential
     * (so the assistant still works from the central dashboard).
     */
    private function scopeFor(User $user): Scope
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

    protected function scopeForHistory(Request $request): Scope
    {
        return $this->scopeFor($request->user());
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
}
