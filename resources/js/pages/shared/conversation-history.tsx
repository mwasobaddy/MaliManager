import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Clock, MessageSquare, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { requestAssistantResume } from '@/lib/assistant-resume';

type Conversation = {
    id: number;
    title: string | null;
    created_at: string;
    last_message_at: string;
    message_count: number;
    preview: string | null;
};

type Props = {
    conversations: Conversation[];
    scope: string;
    backUrl: string;
    backLabel: string;
    viewUrl: (id: number) => string;
    newChatUrl: string;
};

export default function ConversationHistory({
    conversations,
    scope,
    backUrl,
    backLabel,
    viewUrl,
    newChatUrl,
}: Props) {
    const handleDelete = (id: number) => {
        if (!confirm('Delete this conversation? This cannot be undone.')) {
            return;
        }

        router.delete(`/api/${scope}/assistant/conversations/${id}`, {
            preserveScroll: true,
        });
    };

    const formatDate = (iso: string) => {
        const date = new Date(iso);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

        if (diffDays === 0) {
            return 'Today';
        }

        if (diffDays === 1) {
            return 'Yesterday';
        }

        if (diffDays < 7) {
            return `${diffDays} days ago`;
        }

        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    };

    return (
        <>
            <Head title="Conversation history" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="ghost" size="sm">
                            <Link href={backUrl}>
                                <ArrowLeft className="mr-1 size-4" />
                                {backLabel}
                            </Link>
                        </Button>
                        <Heading
                            variant="small"
                            title="Conversation history"
                            description={`${conversations.length} conversation${conversations.length !== 1 ? 's' : ''}`}
                        />
                    </div>
                    <Button asChild>
                        <Link href={newChatUrl}>New chat</Link>
                    </Button>
                </div>

                {conversations.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <MessageSquare className="mb-3 size-10 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">
                                No conversations yet.
                            </p>
                            <Button asChild variant="link" className="mt-2">
                                <Link href={newChatUrl}>
                                    Start your first chat
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-2">
                        {conversations.map((conversation) => (
                            <Card
                                key={conversation.id}
                                className="transition-colors hover:bg-accent/50"
                            >
                                <CardHeader className="pb-2">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <CardTitle className="truncate text-base">
                                                {conversation.title ||
                                                    'Untitled conversation'}
                                            </CardTitle>
                                            <CardDescription className="flex items-center gap-2 text-xs">
                                                <Clock className="size-3" />
                                                {formatDate(
                                                    conversation.last_message_at,
                                                )}
                                                <span className="text-muted-foreground/60">
                                                    ·
                                                </span>
                                                {conversation.message_count}{' '}
                                                message
                                                {conversation.message_count !==
                                                1
                                                    ? 's'
                                                    : ''}
                                            </CardDescription>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    requestAssistantResume({
                                                        conversationId:
                                                            conversation.id,
                                                        title: conversation.title,
                                                    })
                                                }
                                            >
                                                Resume
                                            </Button>
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link
                                                    href={viewUrl(
                                                        conversation.id,
                                                    )}
                                                >
                                                    View
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8 text-muted-foreground hover:text-destructive"
                                                onClick={() =>
                                                    handleDelete(
                                                        conversation.id,
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </CardHeader>
                                {conversation.preview && (
                                    <CardContent className="pt-0">
                                        <p className="line-clamp-2 text-sm text-muted-foreground">
                                            {conversation.preview}
                                        </p>
                                    </CardContent>
                                )}
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
