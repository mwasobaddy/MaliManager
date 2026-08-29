import { Head } from '@inertiajs/react';
import { AssistantChart, type AssistantArtifact } from '@/components/assistant-chart';
import { AssistantChat } from '@/components/assistant-chat';
import { ask as askRoute } from '@/routes/searcher/assistant';

type Props = {
    enabled: boolean;
    quick_prompts?: string[];
    initial_messages?: { role: 'user' | 'assistant' | 'error'; content: string; artifacts?: AssistantArtifact[] }[];
};

const DEFAULT_PROMPTS = [
    'What is my current rent and when is it due?',
    'Show my active lease details.',
    'What maintenance requests have I raised, and their status?',
    'Do I have any unresolved maintenance issues?',
];

export default function Assistant({ enabled, quick_prompts }: Props) {
    if (!enabled) {
        return (
            <>
                <Head title="AI assistant" />
                <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                    <p className="text-sm text-muted-foreground">
                        AI is not configured for your account yet. Add a personal API key in Settings → AI to enable your assistant.
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
                title="My assistant"
                description="Ask about your own leases and maintenance requests. The assistant retrieves your data for you."
                placeholder="Ask about your tenancy…"
                initialMessages={initial_messages}
            />
        </>
    );
}
