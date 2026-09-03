import ConversationDetail from '@/pages/shared/conversation-detail';

type Message = {
    role: 'user' | 'assistant' | 'error';
    content: string;
    artifacts?: unknown[];
};

type Props = {
    conversation: {
        id: number;
        title: string | null;
        created_at: string;
    };
    messages: Message[];
    askUrl: string;
    scope: string;
};

export default function TenantConversationDetail({ conversation, messages, askUrl, scope }: Props) {
    return (
        <ConversationDetail
            conversation={conversation}
            messages={messages}
            askUrl={askUrl}
            scope={scope}
        />
    );
}
