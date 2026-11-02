export type NoteVisibility = 'internal' | 'shared';

export type MaintenanceNote = {
    id: number;
    body: string;
    visibility: NoteVisibility;
    visibility_label: string;
    author_id: number | null;
    author_name: string | null;
    created_at: string | null;
};

export type MaintenanceRequestSummary = {
    id: number;
    title: string;
    status: string;
    priority: string;
    unit_name: string | null;
};

export type VisibilityOption = {
    value: NoteVisibility;
    label: string;
};

export type MaintenanceNoteFormData = {
    body: string;
    visibility: NoteVisibility;
};
