export type AssistantResumeRequest = {
    conversationId: number;
    title: string | null;
};

type ResumeListener = (request: AssistantResumeRequest) => void;

let listeners: ResumeListener[] = [];

export function requestAssistantResume(request: AssistantResumeRequest): void {
    for (const listener of listeners) {
        listener(request);
    }
}

export function onAssistantResume(listener: ResumeListener): () => void {
    listeners.push(listener);

    return () => {
        listeners = listeners.filter((entry) => entry !== listener);
    };
}
