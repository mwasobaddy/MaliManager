import ConversationHistory from '@/pages/shared/conversation-history';
import { page as platformAssistantPage } from '@/routes/platform/assistant';

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
};

export default function PlatformConversationHistory({ conversations, scope }: Props) {
    return (
        <ConversationHistory
            conversations={conversations}
            scope={scope}
            backUrl={platformAssistantPage().url}
            backLabel="Assistant"
            resumeUrl={(id) => `/platform/assistant/conversations/${id}`}
            newChatUrl="/platform/assistant"
        />
    );
}
