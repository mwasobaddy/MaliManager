import { Head } from '@inertiajs/react';
import { AssistantChart, type AssistantArtifact } from '@/components/assistant-chart';
import { AssistantChat } from '@/components/assistant-chat';
import { ask as askRoute } from '@/routes/tenant/assistant';

type Props = {
    enabled: boolean;
    quick_prompts?: string[];
    initial_messages?: { role: 'user' | 'assistant' | 'error'; content: string; artifacts?: AssistantArtifact[] }[];
};

const DEFAULT_PROMPTS = [
    'How many units are vacant right now, by property?',
    'What rent is outstanding this month?',
    'Which leases expire in the next 60 days?',
    'Show my open maintenance backlog by priority.',
    'Summarise expenses by category this year.',
];

export default function Assistant({ enabled, quick_prompts }: Props) {
    if (!enabled) {
        return (
            <>
                <Head title="AI assistant" />
                <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                    <p className="text-sm text-muted-foreground">
                        AI is not configured yet. An owner can add an API key under Organization → AI settings, or you can add a personal
                        key in Settings → AI.
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="AI assistant" />
            <AssistantChat
                askUrl={askRoute().url}
                quickPrompts={quick_prompts ?? DEFAULT_PROMPTS}
                title="AI assistant"
                description="Ask about your portfolio — vacancy, expenses, leases, maintenance. The assistant retrieves live data for you."
                placeholder="Ask about your portfolio…"
                initialMessages={initial_messages}
            />
        </>
    );
}
