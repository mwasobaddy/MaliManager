import { ArrowDown, Send, SquarePen } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { AssistantChart } from '@/components/assistant-chart';
import type { AssistantArtifact } from '@/components/assistant-chart';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Message = {
    role: 'user' | 'assistant' | 'error';
    text: string;
    artifacts?: AssistantArtifact[];
};

type InitialMessage = {
    role: 'user' | 'assistant' | 'error';
    content: string;
    artifacts?: AssistantArtifact[];
};

type Props = {
    askUrl: string;
    quickPrompts: string[];
    title: string;
    description: string;
    placeholder?: string;
    initialMessages?: InitialMessage[];
    initialConversationId?: number | null;
    headerActions?: ReactNode;
};

export function AssistantChat({
    askUrl,
    quickPrompts,
    title,
    description,
    placeholder,
    initialMessages = [],
    initialConversationId = null,
    headerActions,
}: Props) {
    const [messages, setMessages] = useState<Message[]>(() =>
        initialMessages.map((message) => ({
            role: message.role,
            text: message.content,
            artifacts: message.artifacts,
        })),
    );
    const [question, setQuestion] = useState('');
    const [busy, setBusy] = useState(false);
    const [conversationId, setConversationId] = useState<number | null>(
        initialConversationId,
    );
    const [pendingNew, setPendingNew] = useState(false);
    const scrollRef = useRef<HTMLDivElement>(null);
    const stickToBottomRef = useRef(true);
    const [showScrollButton, setShowScrollButton] = useState(false);

    const scrollToBottom = (behavior: ScrollBehavior = 'smooth') => {
        const el = scrollRef.current;

        if (!el) {
            return;
        }

        el.scrollTo({ top: el.scrollHeight, behavior });
    };

    const handleScroll = () => {
        const el = scrollRef.current;

        if (!el) {
            return;
        }

        const distance = el.scrollHeight - el.scrollTop - el.clientHeight;
        const nearBottom = distance < 120;

        stickToBottomRef.current = nearBottom;
        setShowScrollButton(!nearBottom);
    };

    useEffect(() => {
        if (stickToBottomRef.current) {
            requestAnimationFrame(() => scrollToBottom());
        }
    }, [messages]);

    const startNewChat = () => {
        if (busy) {
            return;
        }

        setMessages([]);
        setConversationId(null);
        setPendingNew(true);
    };

    const send = (text: string) => {
        const trimmed = text.trim();

        if (!trimmed || busy) {
            return;
        }

        setMessages((prev) => [...prev, { role: 'user', text: trimmed }]);
        setQuestion('');
        setBusy(true);

        const csrf =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content ?? '';

        const body: Record<string, unknown> = { question: trimmed };

        if (conversationId !== null) {
            body.conversation_id = conversationId;
        }

        if (pendingNew) {
            body.new_conversation = true;
        }

        fetch(askUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(body),
        })
            .then(async (response) => response.json())
            .then((data) => {
                if (data.conversation_id) {
                    setConversationId(data.conversation_id);
                }

                setPendingNew(false);

                if (data.answer) {
                    setMessages((prev) => [
                        ...prev,
                        {
                            role: 'assistant',
                            text: data.answer,
                            artifacts: data.artifacts ?? [],
                        },
                    ]);

                    return;
                }

                setMessages((prev) => [
                    ...prev,
                    {
                        role: 'error',
                        text: data.detail
                            ? `${data.error ?? 'Something went wrong.'} (${data.detail})`
                            : (data.error ?? 'Something went wrong.'),
                    },
                ]);
            })
            .catch(() => {
                setPendingNew(false);
                setMessages((prev) => [
                    ...prev,
                    { role: 'error', text: 'Something went wrong.' },
                ]);
            })
            .finally(() => {
                setBusy(false);
            });
    };

    const hasConversation = messages.length > 0;

    return (
        <div className="flex h-full flex-1 flex-col gap-4 overflow-hidden rounded-xl p-4">
            <div className="flex items-start justify-between gap-3">
                <Heading
                    variant="small"
                    title={title}
                    description={description}
                />
                <div className="flex items-center gap-2">
                    {headerActions}
                    {hasConversation && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="gap-2"
                            onClick={startNewChat}
                            disabled={busy}
                        >
                            <SquarePen className="size-4" />
                            New chat
                        </Button>
                    )}
                </div>
            </div>

            <div className="flex min-h-0 flex-1 flex-col gap-3 rounded-xl border border-input p-4">
                <div className="relative min-h-0 flex-1">
                    <div
                        ref={scrollRef}
                        onScroll={handleScroll}
                        className="h-full space-y-3 overflow-y-auto"
                    >
                        {messages.length === 0 && (
                            <div className="space-y-2">
                                <p className="text-sm text-muted-foreground">
                                    Try asking:
                                </p>
                                {quickPrompts.map((suggestion) => (
                                    <button
                                        key={suggestion}
                                        type="button"
                                        onClick={() => send(suggestion)}
                                        className="block rounded-md border border-input px-3 py-1.5 text-left text-sm hover:bg-accent"
                                    >
                                        {suggestion}
                                    </button>
                                ))}
                            </div>
                        )}

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
                                    {message.text}
                                </div>

                                {message.artifacts?.map(
                                    (artifact, artifactIndex) => (
                                        <div
                                            key={artifactIndex}
                                            className="max-w-[95%] rounded-lg border border-input bg-background p-2"
                                        >
                                            <AssistantChart
                                                artifact={artifact}
                                            />
                                        </div>
                                    ),
                                )}
                            </div>
                        ))}

                        {busy && (
                            <p className="text-xs text-muted-foreground">
                                Thinking…
                            </p>
                        )}
                    </div>

                    {showScrollButton && (
                        <button
                            type="button"
                            onClick={() => scrollToBottom()}
                            className="absolute bottom-3 left-1/2 flex size-8 -translate-x-1/2 items-center justify-center rounded-full border border-input bg-background text-muted-foreground shadow-sm transition hover:bg-accent"
                            aria-label="Scroll to bottom"
                        >
                            <ArrowDown className="size-4" />
                        </button>
                    )}
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        send(question);
                    }}
                    className="flex gap-2 border-t pt-3"
                >
                    <Input
                        value={question}
                        onChange={(e) => setQuestion(e.target.value)}
                        placeholder={placeholder ?? 'Ask a question…'}
                        disabled={busy}
                    />
                    <Button
                        type="submit"
                        size="icon"
                        disabled={busy || !question.trim()}
                    >
                        <Send className="size-4" />
                    </Button>
                </form>
            </div>
        </div>
    );
}
