import { usePage } from '@inertiajs/react';
import { Sparkles, X } from 'lucide-react';
import { useState } from 'react';
import { AssistantChat } from '@/components/assistant-chat';
import { ask as askAdmin } from '@/routes/platform/assistant';
import { ask as askSearcher } from '@/routes/searcher/assistant';
import { ask as askTenant } from '@/routes/tenant/assistant';

type AssistantShared = {
    enabled?: boolean;
    scope?: 'tenant' | 'admin' | 'searcher';
    title?: string;
    description?: string;
    prompts?: string[];
};

const askRoutes = {
    tenant: askTenant,
    admin: askAdmin,
    searcher: askSearcher,
} as const;

export function AssistantWidget() {
    const assistant = usePage().props.assistant as AssistantShared | undefined;
    const [open, setOpen] = useState(false);

    if (!assistant?.enabled || !assistant.scope) {
        return null;
    }

    const askUrl = askRoutes[assistant.scope]().url;

    return (
        <>
            {open && (
                <div className="fixed bottom-[5.5rem] right-4 z-50 flex h-[72vh] w-[380px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-border bg-background shadow-2xl">
                    <div className="min-h-0 flex-1">
                        <AssistantChat
                            askUrl={askUrl}
                            quickPrompts={assistant.prompts ?? []}
                            title={assistant.title ?? 'Assistant'}
                            description={assistant.description ?? ''}
                            initialMessages={[]}
                        />
                    </div>
                </div>
            )}

            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className="fixed bottom-4 right-4 z-50 flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-xl transition hover:scale-105"
                aria-label={open ? 'Close AI assistant' : 'Open AI assistant'}
            >
                {open ? <X className="size-6" /> : <Sparkles className="size-6" />}
            </button>
        </>
    );
}
