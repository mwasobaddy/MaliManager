import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as auditIndex } from '@/routes/tenant/audit';

type Audit = {
    id: number;
    created_at: string;
    event: string | null;
    log_name: string | null;
    description: string | null;
    ip_address: string | null;
    user_agent: string | null;
    causer: { id: number; name: string; email: string } | null;
    subject: { type: string; id: number } | null;
    properties: Record<string, unknown> | null;
    subject_type: string | null;
    subject_id: number | null;
};

type Props = {
    audit: Audit;
};

export default function AuditShow({ audit }: Props) {
    return (
        <>
            <Head title="Audit entry" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading variant="small" title="Audit entry" description="Full details of a single recorded action." />
                    <Button asChild variant="outline">
                        <Link href={auditIndex()}>
                            <ArrowLeft className="size-4" />
                            Back to audit log
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Overview</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <Row label="When" value={new Date(audit.created_at).toLocaleString()} />
                            <Row label="Action" value={audit.event ?? '—'} />
                            <Row label="Category" value={audit.log_name ?? '—'} />
                            <Row label="Description" value={audit.description ?? '—'} />
                            <Row
                                label="Actor"
                                value={audit.causer ? `${audit.causer.name} (${audit.causer.email})` : 'System'}
                            />
                            <Row
                                label="Resource"
                                value={audit.subject ? `${audit.subject.type} #${audit.subject.id}` : '—'}
                            />
                            <Row label="IP address" value={audit.ip_address ?? '—'} />
                            <Row label="User agent" value={audit.user_agent ?? '—'} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Context</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <Row label="Subject type" value={audit.subject_type ?? '—'} />
                            <Row label="Subject id" value={audit.subject_id ?? '—'} />
                            {audit.properties && (
                                <pre className="mt-3 overflow-x-auto rounded bg-muted p-3 text-xs">
                                    {JSON.stringify(audit.properties, null, 2)}
                                </pre>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-4 border-b pb-2 last:border-0">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value}</span>
        </div>
    );
}

AuditShow.layout = {
    breadcrumbs: [
        { title: 'Audit log', href: auditIndex() },
        { title: 'Entry' },
    ],
};
