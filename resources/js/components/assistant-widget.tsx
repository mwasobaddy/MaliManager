import { Link, usePage } from '@inertiajs/react';
import { History, Sparkles, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { AssistantArtifact } from '@/components/assistant-chart';
import { AssistantChat } from '@/components/assistant-chat';
import { Button } from '@/components/ui/button';
import { onAssistantResume } from '@/lib/assistant-resume';
import {
    ask as askAdmin,
    conversation as conversationAdmin,
    history as historyAdmin,
} from '@/routes/platform/assistant';
import {
    ask as askSearcher,
    conversation as conversationSearcher,
    history as historySearcher,
} from '@/routes/searcher/assistant';
import {
    ask as askTenant,
    conversation as conversationTenant,
    history as historyTenant,
} from '@/routes/tenant/assistant';

type AssistantShared = {
    enabled?: boolean;
    scope?: 'tenant' | 'admin' | 'searcher';
    title?: string;
    description?: string;
    prompts?: string[];
};

type ResumedConversation = {
    id: number;
    title: string | null;
    messages: {
        role: 'user' | 'assistant' | 'error';
        content: string;
        artifacts?: AssistantArtifact[];
    }[];
};

const askRoutes = {
    tenant: askTenant,
    admin: askAdmin,
    searcher: askSearcher,
} as const;

const historyRoutes = {
    tenant: historyTenant,
    admin: historyAdmin,
    searcher: historySearcher,
} as const;

const conversationRoutes = {
    tenant: conversationTenant,
    admin: conversationAdmin,
    searcher: conversationSearcher,
} as const;

export function AssistantWidget() {
    const assistant = usePage().props.assistant as AssistantShared | undefined;
    const [open, setOpen] = useState(false);
    const [resumed, setResumed] = useState<ResumedConversation | null>(null);

    useEffect(() => {
        return onAssistantResume(({ conversationId, title }) => {
            const fetchUrl =
                conversationRoutes[assistant?.scope ?? 'tenant'](
                    conversationId,
                ).url;

            fetch(fetchUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(async (response) => response.json())
                .then((data) => {
                    setResumed({
                        id: data.id,
                        title: data.title ?? title,
                        messages: data.messages ?? [],
                    });
                    setOpen(true);
                })
                .catch(() => {
                    setResumed({
                        id: conversationId,
                        title: title,
                        messages: [],
                    });
                    setOpen(true);
                });
        });
    }, [assistant?.scope]);

    if (!assistant?.enabled || !assistant.scope) {
        return null;
    }

    const askUrl = askRoutes[assistant.scope]().url;
    const historyUrl = historyRoutes[assistant.scope]().url;

    return (
        <>
            {open && (
                <div className="fixed right-4 bottom-[5.5rem] z-50 flex h-[72vh] w-[380px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-border bg-background shadow-2xl">
                    <div className="min-h-0 flex-1">
                        <AssistantChat
                            key={resumed?.id ?? 'fresh'}
                            askUrl={askUrl}
                            quickPrompts={assistant.prompts ?? []}
                            title={
                                resumed?.title ?? assistant.title ?? 'Assistant'
                            }
                            description={
                                resumed
                                    ? 'Continuing a previous conversation.'
                                    : (assistant.description ?? '')
                            }
                            initialMessages={resumed?.messages ?? []}
                            initialConversationId={resumed?.id ?? null}
                            headerActions={
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={historyUrl} className="gap-2">
                                        <History className="size-4" />
                                        History
                                    </Link>
                                </Button>
                            }
                        />
                    </div>
                </div>
            )}

            <button
                type="button"
                onClick={() => {
                    setResumed(null);
                    setOpen((value) => !value);
                }}
                className="fixed right-4 bottom-4 z-50 flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-xl transition hover:scale-105"
                aria-label={open ? 'Close AI assistant' : 'Open AI assistant'}
            >
                {open ? (
                    <X className="size-6" />
                ) : (
                    <Sparkles className="size-6" />
                )}
            </button>
        </>
    );
}
