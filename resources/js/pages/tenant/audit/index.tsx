import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, History } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { exportMethod as auditExport, index as auditIndex, show as auditShow } from '@/routes/tenant/audit';

type Causer = { id: number; name: string; email: string } | null;
type Subject = { type: string; id: number } | null;

type Audit = {
    id: number;
    created_at: string;
    event: string | null;
    log_name: string | null;
    description: string | null;
    ip_address: string | null;
    causer: Causer;
    subject: Subject;
};

type Paginator = {
    data: Audit[];
    meta: { current_page: number; last_page: number; total: number };
};

type Props = {
    audits: Paginator;
    filters: Record<string, string>;
    canExport: boolean;
    options: {
        events: string[];
        subjectTypes: Record<string, string>;
        actors: { id: number; name: string; email: string }[];
    };
};

export default function AuditIndex({ audits, filters, canExport, options }: Props) {
    const form = useForm({
        actor: filters.actor ?? '',
        event: filters.event ?? '',
        log_name: filters.log_name ?? '',
        subject_type: filters.subject_type ?? '',
        search: filters.search ?? '',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
    });

    const submit = () => {
        form.get(auditIndex(), { preserveState: true, replace: true });
    };

    const goToPage = (page: number) => {
        router.get(
            auditIndex(),
            { ...form.data, page },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Audit log" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Audit log"
                        description="A record of actions taken across your organization."
                    />
                    {canExport && (
                        <Button asChild variant="outline">
                            <a href={auditExport({ query: form.data })}>
                                <Download className="size-4" />
                                Export CSV
                            </a>
                        </Button>
                    )}
                </div>

                <Card>
                    <CardContent className="space-y-4 py-4">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <input
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                placeholder="Search description"
                                value={form.data.search}
                                onChange={(e) => form.setData('search', e.target.value)}
                            />
                            <input
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                placeholder="Actor name or email"
                                value={form.data.actor}
                                onChange={(e) => form.setData('actor', e.target.value)}
                            />
                            <select
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                value={form.data.event}
                                onChange={(e) => form.setData('event', e.target.value)}
                            >
                                <option value="">Any action</option>
                                {options.events.map((event) => (
                                    <option key={event} value={event}>
                                        {event}
                                    </option>
                                ))}
                            </select>
                            <select
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                value={form.data.subject_type}
                                onChange={(e) => form.setData('subject_type', e.target.value)}
                            >
                                <option value="">Any resource</option>
                                {Object.entries(options.subjectTypes).map(([type, label]) => (
                                    <option key={type} value={type}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            <input
                                type="date"
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                value={form.data.date_from}
                                onChange={(e) => form.setData('date_from', e.target.value)}
                            />
                            <input
                                type="date"
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                value={form.data.date_to}
                                onChange={(e) => form.setData('date_to', e.target.value)}
                            />
                        </div>
                        <div className="flex gap-2">
                            <Button type="button" onClick={submit}>
                                Apply filters
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => {
                                    form.reset();
                                    form.get(auditIndex(), { preserveState: true, replace: true });
                                }}
                            >
                                Clear
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="py-4">
                        {audits.data.length === 0 ? (
                            <div className="py-16 text-center">
                                <History className="mx-auto size-10 text-muted-foreground" />
                                <p className="mt-4 font-medium">No audit entries yet</p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Actions across your organization will appear here.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <th className="px-3 py-2">Time</th>
                                            <th className="px-3 py-2">Actor</th>
                                            <th className="px-3 py-2">Action</th>
                                            <th className="px-3 py-2">Resource</th>
                                            <th className="px-3 py-2">Description</th>
                                            <th className="px-3 py-2">IP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {audits.data.map((audit) => (
                                            <tr key={audit.id} className="border-b last:border-0">
                                                <td className="whitespace-nowrap px-3 py-2 text-muted-foreground">
                                                    {new Date(audit.created_at).toLocaleString()}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {audit.causer ? (
                                                        <Link
                                                            className="text-foreground hover:underline"
                                                            href={auditShow({ activity: audit.id })}
                                                        >
                                                            {audit.causer.name}
                                                        </Link>
                                                    ) : (
                                                        <span className="text-muted-foreground">System</span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <span className="rounded bg-muted px-2 py-0.5 text-xs">
                                                        {audit.event}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2">
                                                    {audit.subject
                                                        ? `${audit.subject.type} #${audit.subject.id}`
                                                        : '—'}
                                                </td>
                                                <td className="px-3 py-2">{audit.description}</td>
                                                <td className="whitespace-nowrap px-3 py-2 text-muted-foreground">
                                                    {audit.ip_address ?? '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {audits.meta.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Page {audits.meta.current_page} of {audits.meta.last_page}
                                </span>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={audits.meta.current_page <= 1}
                                        onClick={() => goToPage(audits.meta.current_page - 1)}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={audits.meta.current_page >= audits.meta.last_page}
                                        onClick={() => goToPage(audits.meta.current_page + 1)}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AuditIndex.layout = {
    breadcrumbs: [{ title: 'Audit log' }],
};
