import ConversationHistory from '@/pages/shared/conversation-history';
import { page as tenantAssistantPage } from '@/routes/tenant/assistant';

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

export default function TenantConversationHistory({ conversations, scope }: Props) {
    return (
        <ConversationHistory
            conversations={conversations}
            scope={scope}
            backUrl={tenantAssistantPage().url}
            backLabel="Assistant"
            viewUrl={(id) => `/assistant/conversations/${id}`}
            newChatUrl="/assistant"
        />
    );
}
