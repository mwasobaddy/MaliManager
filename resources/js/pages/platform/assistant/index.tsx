import { Head } from '@inertiajs/react';
import type { AssistantArtifact } from '@/components/assistant-chart';
import { AssistantChat } from '@/components/assistant-chat';
import { ask as askRoute } from '@/routes/platform/assistant';

type Props = {
    enabled: boolean;
    quick_prompts?: string[];
    initial_messages?: { role: 'user' | 'assistant' | 'error'; content: string; artifacts?: AssistantArtifact[] }[];
};

const DEFAULT_PROMPTS = [
    'How many organizations are active on the platform?',
    'What is the total occupied vs vacant unit ratio across all orgs?',
    'Which organizations have the most open maintenance requests?',
    'Total rent potential collected vs outstanding, platform-wide.',
    'Leases expiring in the next 60 days across all organizations.',
];

export default function Assistant({ enabled, quick_prompts, initial_messages }: Props) {
    if (!enabled) {
        return (
            <>
                <Head title="Platform assistant" />
                <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                    <p className="text-sm text-muted-foreground">
                        The platform assistant is not configured. Add a personal API key in Settings → AI, or configure a platform-wide key.
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Platform assistant" />
            <AssistantChat
                askUrl={askRoute().url}
                quickPrompts={quick_prompts ?? DEFAULT_PROMPTS}
                title="Platform assistant"
                description="Analyse data across every organization on the platform. The assistant retrieves live data for you."
                placeholder="Ask about the platform…"
                initialMessages={initial_messages}
            />
        </>
    );
}
