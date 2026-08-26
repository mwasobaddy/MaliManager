import { Head, Link, router } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { Wrench } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type RequestRow = {
    id: number;
    title: string;
    description: string;
    unit_name: string | null;
    raised_by: string | null;
    assigned_to: number | null;
    assignee_name: string | null;
    status: string;
    priority: string;
    resolution_notes: string | null;
    photo_count: number;
    allowed_transitions: string[];
};

type StaffOption = { id: number; name: string };

type Props = {
    requests: {
        data: RequestRow[];
        current_page: number;
        last_page: number;
    };
    filters: { status: string; priority: string };
    statuses: string[];
    priorities: string[];
    staff: StaffOption[];
};

const priorityStyles: Record<string, string> = {
    low: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
    medium: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
    urgent: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};

export default function MaintenanceIndex({ requests, filters, statuses, priorities, staff }: Props) {
    const { context } = usePage().props;
    const canEdit = (context?.permissions ?? []).includes('maintenance.edit');
    const canDelete = (context?.permissions ?? []).includes('maintenance.delete');
    const propertySlug = context?.property?.slug ?? '';

    const setFilter = (key: 'status' | 'priority', value: string) => {
        router.get(
            routesIndexUrl(),
            { [key]: value === 'all' ? '' : value },
            { preserveState: true, replace: true },
        );
    };

    const routesIndexUrl = () => {
        // Built inline so both filter params survive.
        const params = new URLSearchParams();

        if (filters.status) {
            params.set('status', filters.status);
        }

        if (filters.priority) {
            params.set('priority', filters.priority);
        }

        const query = params.toString();

        return `/maintenance${query ? `?${query}` : ''}`;
    };

    return (
        <>
            <Head title="Maintenance" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Maintenance"
                        description="Requests raised by occupants and staff across your properties."
                    />
                    <div className="flex gap-2">
                        <Select value={filters.status || 'all'} onValueChange={(v) => setFilter('status', v)}>
                            <SelectTrigger className="w-40"><SelectValue placeholder="Status" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                {statuses.map((s) => (
                                    <SelectItem key={s} value={s}>{s.replace('_', ' ')}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={filters.priority || 'all'} onValueChange={(v) => setFilter('priority', v)}>
                            <SelectTrigger className="w-40"><SelectValue placeholder="Priority" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All priorities</SelectItem>
                                {priorities.map((p) => (
                                    <SelectItem key={p} value={p} className="capitalize">{p}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {requests.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No maintenance requests found.</p>
                ) : (
                    <div className="space-y-3">
                        {requests.data.map((item) => (
                            <div
                                key={item.id}
                                data-test={`maintenance-${item.id}`}
                                className="rounded-xl border border-input p-4"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{item.title}</span>
                                            <span className={`rounded px-1.5 py-0.5 text-xs capitalize ${priorityStyles[item.priority] ?? ''}`}>
                                                    {item.priority}
                                                </span>
                                            <Badge variant="outline" className="capitalize">{item.status.replace('_', ' ')}</Badge>
                                            {item.photo_count > 0 && (
                                                <span className="text-xs text-muted-foreground">
                                                    {item.photo_count} photo{item.photo_count > 1 ? 's' : ''}
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-1 line-clamp-2 max-w-prose text-sm text-muted-foreground">
                                            {item.description}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Raised by {item.raised_by ?? '—'}
                                            {item.unit_name ? ` · ${item.unit_name}` : ''}
                                            {item.assignee_name ? ` · assigned to ${item.assignee_name}` : ''}
                                        </p>
                                    </div>

                                    <div className="flex shrink-0 items-center gap-2">
                                        {canEdit && (
                                            <>
                                                <Wrench className="hidden size-4 text-muted-foreground sm:block" />
                                                <Select
                                                    value=""
                                                    onValueChange={(value) => {
                                                        if (!value) {
                                                            return;
                                                        }

                                                        router.put(`/maintenance/${item.id}`, {
                                                            assigned_to: Number(value),
                                                        }, { preserveScroll: true });
                                                    }}
                                                >
                                                    <SelectTrigger className="w-40"><SelectValue placeholder="Assign to…" /></SelectTrigger>
                                                    <SelectContent>
                                                        {staff.map((member) => (
                                                            <SelectItem key={member.id} value={String(member.id)}>
                                                                {member.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>

                                                {item.allowed_transitions.length > 0 && (
                                                    <Select
                                                        value=""
                                                        onValueChange={(value) => {
                                                            if (!value) {
                                                                return;
                                                            }

                                                            router.put(`/maintenance/${item.id}`, {
                                                                status: value,
                                                                resolution_notes:
                                                                    value === 'resolved' ? window.prompt('Resolution notes:') ?? '' : undefined,
                                                            }, { preserveScroll: true });
                                                        }}
                                                    >
                                                        <SelectTrigger className="w-44"><SelectValue placeholder="Move to…" /></SelectTrigger>
                                                        <SelectContent>
                                                            {item.allowed_transitions.map((next) => (
                                                                <SelectItem key={next} value={next}>
                                                                    {next === 'in_progress'
                                                                        ? 'Start work'
                                                                        : next.charAt(0).toUpperCase() + next.slice(1)}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                )}
                                            </>
                                        )}

                                        {canDelete && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-red-600"
                                                onClick={() =>
                                                    router.delete(`/maintenance/${item.id}`, { preserveScroll: true })
                                                }
                                            >
                                                Delete
                                            </Button>
                                        )}

                                        {!canEdit && !canDelete && propertySlug && (
                                            <Button asChild variant="ghost" size="sm">
                                                <Link href={`/properties`}>—</Link>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {requests.last_page > 1 && (
                    <div className="flex gap-1 text-sm">
                        {Array.from({ length: requests.last_page }, (_, i) => i + 1).map((page) => (
                            <Button
                                key={page}
                                variant={page === requests.current_page ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => router.get(routesIndexUrl(), { page }, { preserveScroll: true })}
                            >
                                {page}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
