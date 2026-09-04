import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Clock } from 'lucide-react';
import { AssistantChart } from '@/components/assistant-chart';
import type { AssistantArtifact } from '@/components/assistant-chart';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Message = {
    role: 'user' | 'assistant' | 'error';
    content: string;
    artifacts?: AssistantArtifact[];
};

type Props = {
    conversation: {
        id: number;
        title: string | null;
        created_at: string;
    };
    messages: Message[];
    askUrl: string;
};

export default function ConversationDetail({ conversation, messages }: Props) {
    const historyUrl = `/assistant/history`;

    return (
        <>
            <Head title={conversation.title || 'Conversation'} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-hidden rounded-xl p-4">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="ghost" size="sm">
                            <Link href={historyUrl}>
                                <ArrowLeft className="mr-1 size-4" />
                                History
                            </Link>
                        </Button>
                        <Heading
                            variant="small"
                            title={
                                conversation.title || 'Untitled conversation'
                            }
                            description={
                                <span className="flex items-center gap-1.5">
                                    <Clock className="size-3" />
                                    {new Date(
                                        conversation.created_at,
                                    ).toLocaleDateString('en-US', {
                                        month: 'long',
                                        day: 'numeric',
                                        year: 'numeric',
                                    })}
                                </span>
                            }
                        />
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link href={`/assistant`}>Continue chatting</Link>
                    </Button>
                </div>

                <div className="min-h-0 flex-1 space-y-3 overflow-y-auto rounded-xl border border-input p-4">
                    {messages.map((message, index) => (
                        <div key={index} className="space-y-2">
                            <div
                                className={`max-w-[85%] rounded-lg px-3 py-2 text-sm ${
                                    message.role === 'user'
                                        ? 'ml-auto bg-primary text-primary-foreground'
                                        : message.role === 'error'
                                          ? 'bg-destructive/10 text-destructive'
                                          : 'bg-muted'
                                }`}
                            >
                                {message.content}
                            </div>

                            {message.artifacts?.map(
                                (artifact, artifactIndex) => (
                                    <div
                                        key={artifactIndex}
                                        className="max-w-[95%] rounded-lg border border-input bg-background p-2"
                                    >
                                        <AssistantChart artifact={artifact} />
                                    </div>
                                ),
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}
