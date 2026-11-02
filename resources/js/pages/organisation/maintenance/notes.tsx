import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, MessageSquare } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import * as noteRoutes from '@/routes/tenant/maintenance/notes';
import type {
    MaintenanceNoteFormData,
    MaintenanceNote,
    MaintenanceRequestSummary,
    NoteVisibility,
    VisibilityOption,
} from '@/types/maintenance-note';

type Props = {
    request: MaintenanceRequestSummary;
    notes: MaintenanceNote[];
    visibilities: VisibilityOption[];
};

/**
 * Notes on one maintenance request.
 *
 * The server requires a body of 2–5000 characters and a known visibility; the
 * form enforces the same two constraints before submitting, and `maxLength`
 * stops a long paste from being silently rejected.
 */
export default function MaintenanceNotes({
    request,
    notes,
    visibilities,
}: Props) {
    const form = useForm<MaintenanceNoteFormData>({
        body: '',
        visibility: 'internal',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(noteRoutes.store.url({ record: request.id }), {
            preserveScroll: true,
            onSuccess: () => form.reset('body'),
        });
    };

    return (
        <>
            <Head title={`Notes — ${request.title}`} />

            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
                <div>
                    <Link
                        href="/maintenance"
                        className="text-muted-foreground hover:text-accent-text inline-flex items-center gap-1.5 text-sm"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                        Back to maintenance
                    </Link>
                </div>

                <Heading
                    title={request.title}
                    description={
                        request.unit_name
                            ? `Unit ${request.unit_name}`
                            : 'Maintenance request'
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <Badge variant="neutral">{request.status}</Badge>
                    <Badge variant="info">{request.priority}</Badge>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base font-medium">
                            Add a note
                        </CardTitle>
                        <CardDescription>
                            Internal notes are staff-only; shared notes are
                            visible to the occupant.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="body">Note</Label>
                                <Textarea
                                    id="body"
                                    rows={3}
                                    maxLength={5000}
                                    value={form.data.body}
                                    onChange={(event) =>
                                        form.setData('body', event.target.value)
                                    }
                                    aria-invalid={!!form.errors.body}
                                    placeholder="What happened, and what is next?"
                                />
                                {form.errors.body && (
                                    <p className="text-status-danger text-sm">
                                        {form.errors.body}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-wrap items-end justify-between gap-3">
                                <div className="space-y-2">
                                    <Label htmlFor="visibility">
                                        Visibility
                                    </Label>
                                    <Select
                                        value={form.data.visibility}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'visibility',
                                                value as NoteVisibility,
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="visibility"
                                            className="w-56"
                                            aria-invalid={
                                                !!form.errors.visibility
                                            }
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {visibilities.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.visibility && (
                                        <p className="text-status-danger text-sm">
                                            {form.errors.visibility}
                                        </p>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Add note
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base font-medium">
                            History
                        </CardTitle>
                        <CardDescription>
                            {notes.length}{' '}
                            {notes.length === 1 ? 'note' : 'notes'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {notes.length === 0 ? (
                            <Empty
                                compact
                                icon={<MessageSquare className="size-4" />}
                                title="No notes yet"
                                description="Notes record what was tried, who was contacted and when someone is coming."
                            />
                        ) : (
                            <ul className="space-y-4">
                                {notes.map((note) => (
                                    <li
                                        key={note.id}
                                        className="border-border/60 border-b pb-4 last:border-b-0 last:pb-0"
                                    >
                                        <div className="mb-1 flex flex-wrap items-center gap-2">
                                            <span className="text-sm font-medium">
                                                {note.author_name ??
                                                    'Removed user'}
                                            </span>
                                            <Badge
                                                variant={
                                                    note.visibility ===
                                                    'shared'
                                                        ? 'accent'
                                                        : 'neutral'
                                                }
                                            >
                                                {note.visibility_label}
                                            </Badge>
                                            {note.created_at && (
                                                <span className="text-muted-foreground text-xs">
                                                    {new Date(
                                                        note.created_at,
                                                    ).toLocaleString()}
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-sm whitespace-pre-line">
                                            {note.body}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
