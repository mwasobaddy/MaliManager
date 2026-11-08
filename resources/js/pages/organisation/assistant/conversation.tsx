import ConversationDetail from '@/pages/shared/conversation-detail';
import type { ConversationMessage } from '@/pages/shared/conversation-detail';

type Props = {
    conversation: {
        id: number;
        title: string | null;
        created_at: string;
    };
    messages: ConversationMessage[];
};

export default function TenantConversationDetail({
    conversation,
    messages,
}: Props) {
    return (
        <ConversationDetail
            conversation={conversation}
            messages={messages}
            historyUrl="/assistant/history"
            assistantUrl="/assistant"
        />
    );
}
