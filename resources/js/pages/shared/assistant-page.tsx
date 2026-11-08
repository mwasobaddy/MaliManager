import { Head, Link } from '@inertiajs/react';
import { History } from 'lucide-react';
import type { AssistantArtifact } from '@/components/assistant-chart';
import { AssistantChat } from '@/components/assistant-chat';
import { Button } from '@/components/ui/button';

export type AssistantInitialMessage = {
    role: 'user' | 'assistant' | 'error';
    content: string;
    artifacts?: AssistantArtifact[];
};

type Props = {
    enabled: boolean;
    headTitle: string;
    title: string;
    description: string;
    placeholder: string;
    disabledMessage: string;
    askUrl: string;
    historyUrl: string;
    quickPrompts?: string[];
    initialMessages?: AssistantInitialMessage[];
};

/**
 * The one assistant page, rendered three times.
 *
 * Platform, tenant and searcher index pages were the same shell with
 * different copy and URLs — including a shared bug where two of the three
 * forgot to destructure `initial_messages`, so those surfaces never
 * restored the latest conversation. Per-surface files stay because Inertia
 * page names and Wayfinder routes differ, but they are config-only now:
 * every shape lives here.
 */
export default function AssistantPage({
    enabled,
    headTitle,
    title,
    description,
    placeholder,
    disabledMessage,
    askUrl,
    historyUrl,
    quickPrompts = [],
    initialMessages = [],
}: Props) {
    if (!enabled) {
        return (
            <>
                <Head title={headTitle} />
                <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                    <p className="text-sm text-muted-foreground">
                        {disabledMessage}
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={headTitle} />
            <AssistantChat
                askUrl={askUrl}
                quickPrompts={quickPrompts}
                title={title}
                description={description}
                placeholder={placeholder}
                initialMessages={initialMessages}
                headerActions={
                    <Button asChild variant="ghost" size="sm">
                        <Link href={historyUrl} className="gap-2">
                            <History className="size-4" />
                            History
                        </Link>
                    </Button>
                }
            />
        </>
    );
}
