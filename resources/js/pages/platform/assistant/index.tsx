import AssistantPage from '@/pages/shared/assistant-page';
import type { AssistantInitialMessage } from '@/pages/shared/assistant-page';
import { ask as askRoute } from '@/routes/platform/assistant';

type Props = {
    enabled: boolean;
    quick_prompts?: string[];
    initial_messages?: AssistantInitialMessage[];
};

const DEFAULT_PROMPTS = [
    'How many organizations are active on the platform?',
    'What is the total occupied vs vacant unit ratio across all orgs?',
    'Which organizations have the most open maintenance requests?',
    'Total rent potential collected vs outstanding, platform-wide.',
    'Leases expiring in the next 60 days across all organizations.',
];

export default function Assistant({
    enabled,
    quick_prompts,
    initial_messages,
}: Props) {
    return (
        <AssistantPage
            enabled={enabled}
            headTitle="Platform assistant"
            title="Platform assistant"
            description="Analyse data across every organization on the platform. The assistant retrieves live data for you."
            placeholder="Ask about the platform…"
            disabledMessage="The platform assistant is not configured. Add a personal API key in Settings → AI, or configure a platform-wide key."
            askUrl={askRoute().url}
            historyUrl="/platform/assistant/history"
            quickPrompts={quick_prompts ?? DEFAULT_PROMPTS}
            initialMessages={initial_messages}
        />
    );
}
