import AssistantPage from '@/pages/shared/assistant-page';
import type { AssistantInitialMessage } from '@/pages/shared/assistant-page';
import { ask as askRoute } from '@/routes/searcher/assistant';

type Props = {
    enabled: boolean;
    quick_prompts?: string[];
    initial_messages?: AssistantInitialMessage[];
};

const DEFAULT_PROMPTS = [
    'What is my current rent and when is it due?',
    'Show my active lease details.',
    'What maintenance requests have I raised, and their status?',
    'Do I have any unresolved maintenance issues?',
];

export default function Assistant({
    enabled,
    quick_prompts,
    initial_messages,
}: Props) {
    return (
        <AssistantPage
            enabled={enabled}
            headTitle="AI assistant"
            title="My assistant"
            description="Ask about your own leases and maintenance requests. The assistant retrieves your data for you."
            placeholder="Ask about your tenancy…"
            disabledMessage="AI is not configured for your account yet. Add a personal API key in Settings → AI to enable your assistant."
            askUrl={askRoute().url}
            historyUrl="/searcher/assistant/history"
            quickPrompts={quick_prompts ?? DEFAULT_PROMPTS}
            initialMessages={initial_messages}
        />
    );
}
