import AssistantPage from '@/pages/shared/assistant-page';
import type { AssistantInitialMessage } from '@/pages/shared/assistant-page';
import { ask as askRoute } from '@/routes/tenant/assistant';

type Props = {
    enabled: boolean;
    quick_prompts?: string[];
    initial_messages?: AssistantInitialMessage[];
};

const DEFAULT_PROMPTS = [
    'How many units are vacant right now, by property?',
    'What rent is outstanding this month?',
    'Which leases expire in the next 60 days?',
    'Show my open maintenance backlog by priority.',
    'Summarise expenses by category this year.',
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
            title="AI assistant"
            description="Ask about your portfolio — vacancy, expenses, leases, maintenance. The assistant retrieves live data for you."
            placeholder="Ask about your portfolio…"
            disabledMessage="AI is not configured yet. An owner can add an API key under Organization → AI settings, or you can add a personal key in Settings → AI."
            askUrl={askRoute().url}
            historyUrl="/assistant/history"
            quickPrompts={quick_prompts ?? DEFAULT_PROMPTS}
            initialMessages={initial_messages}
        />
    );
}

Assistant.layout = {
    breadcrumbs: [{ title: 'Assistant' }],
};
