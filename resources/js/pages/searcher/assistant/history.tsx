import ConversationHistory from '@/pages/shared/conversation-history';
import { page as searcherAssistantPage } from '@/routes/searcher/assistant';

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

export default function SearcherConversationHistory({
    conversations,
    scope,
}: Props) {
    return (
        <ConversationHistory
            conversations={conversations}
            scope={scope}
            backUrl={searcherAssistantPage().url}
            backLabel="Assistant"
            viewUrl={(id) => `/searcher/assistant/conversations/${id}`}
            newChatUrl="/searcher/assistant"
        />
    );
}
