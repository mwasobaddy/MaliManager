import { Head } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ask as askRoute, page as assistantPage } from '@/routes/searcher/assistant';

type Message = { role: 'user' | 'assistant' | 'error'; text: string };

type Props = {
    enabled: boolean;
};

const SUGGESTIONS = [
    'When does my lease end?',
    'What is my monthly rent?',
    'What is the status of my maintenance requests?',
];

export default function SearcherAssistant({ enabled }: Props) {
    const [messages, setMessages] = useState<Message[]>([]);
    const [question, setQuestion] = useState('');
    const [busy, setBusy] = useState(false);
    const bottomRef = useRef<HTMLDivElement>(null);

    const send = (text: string) => {
        const trimmed = text.trim();

        if (!trimmed || busy) {
            return;
        }

        setMessages((prev) => [...prev, { role: 'user', text: trimmed }]);
        setQuestion('');
        setBusy(true);

        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

        fetch(askRoute().url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ question: trimmed }),
        })
            .then(async (response) => response.json())
            .then((data) => {
                setMessages((prev) => [
                    ...prev,
                    data.answer
                        ? { role: 'assistant', text: data.answer }
                        : { role: 'error', text: data.error ?? 'Something went wrong.' },
                ]);
            })
            .finally(() => {
                setBusy(false);
                requestAnimationFrame(() => bottomRef.current?.scrollIntoView({ behavior: 'smooth' }));
            });
    };

    return (
        <>
            <Head title="AI assistant" />
            <div className="mx-auto flex h-full w-full max-w-2xl flex-1 flex-col gap-4 p-6">
                <Heading
                    title="AI assistant"
                    description="Ask about your own rentals and maintenance requests."
                />

                {!enabled ? (
                    <p className="text-sm text-muted-foreground">
                        AI is not configured yet. Your property manager can enable it, or you
                        can add a personal API key in Settings → AI.
                    </p>
                ) : (
                    <div className="flex min-h-0 flex-1 flex-col gap-3 rounded-xl border border-input p-4">
                        <div className="min-h-0 flex-1 space-y-3 overflow-y-auto">
                            {messages.length === 0 && (
                                <div className="space-y-2">
                                    <p className="text-sm text-muted-foreground">Try asking:</p>
                                    {SUGGESTIONS.map((suggestion) => (
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
                                <div
                                    key={index}
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
                            ))}
                            {busy && <p className="text-xs text-muted-foreground">Thinking…</p>}
                            <div ref={bottomRef} />
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
                                placeholder="Ask a question…"
                                disabled={busy}
                            />
                            <Button type="submit" size="icon" disabled={busy || !question.trim()}>
                                <Send className="size-4" />
                            </Button>
                        </form>
                    </div>
                )}
            </div>
        </>
    );
}
