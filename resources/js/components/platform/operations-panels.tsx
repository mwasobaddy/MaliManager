import { StatusIcon } from '@/components/status-cell';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { Operations } from './types';

export function OperationsPanels({ operations }: { operations: Operations }) {
    return (
        <>
            <div className="grid items-start gap-4 xl:grid-cols-2">
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base font-medium">
                            Integrations
                        </CardTitle>
                        <CardDescription>
                            Configured means a credential exists — not that the
                            vendor is up.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="divide-y divide-border">
                            {operations.integrations.map((integration) => (
                                <li
                                    key={integration.key}
                                    className="flex items-center gap-2.5 py-2.5 text-sm first:pt-0 last:pb-0"
                                >
                                    <StatusIcon
                                        tone={
                                            integration.configured
                                                ? 'success'
                                                : 'muted'
                                        }
                                        label={
                                            integration.configured
                                                ? 'configured'
                                                : 'not configured'
                                        }
                                        className="shrink-0"
                                    />
                                    <span className="font-medium">
                                        {integration.label}
                                    </span>
                                    <span className="ml-auto text-muted-foreground">
                                        {integration.detail}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base font-medium">
                            Failed jobs
                        </CardTitle>
                        <CardDescription>
                            {operations.failed_jobs.count === 0
                                ? 'Queue is clear.'
                                : `${operations.failed_jobs.count} failed — inspect before retrying.`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {operations.failed_jobs.recent.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                None recorded.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {operations.failed_jobs.recent.map(
                                    (job, index) => (
                                        <li
                                            key={`${job.queue}-${job.age}-${index}`}
                                            className="flex items-center gap-2.5 py-2.5 text-sm first:pt-0 last:pb-0"
                                        >
                                            <StatusIcon
                                                tone="danger"
                                                label="failed"
                                                className="shrink-0"
                                            />
                                            <span className="font-medium">
                                                {job.queue}
                                            </span>
                                            <span className="ml-auto text-muted-foreground">
                                                {job.age}
                                            </span>
                                        </li>
                                    ),
                                )}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-base font-medium">
                        Recent audit activity
                    </CardTitle>
                    <CardDescription>
                        Latest platform log entries, newest first.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {operations.audit.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No audit entries yet.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {operations.audit.map((entry, index) => (
                                <li
                                    key={`${entry.text}-${entry.age}-${index}`}
                                    className="flex items-baseline gap-2.5 py-2 text-sm first:pt-0 last:pb-0"
                                >
                                    <span className="min-w-0 flex-1 truncate">
                                        {entry.text}
                                    </span>
                                    <span className="shrink-0 text-xs whitespace-nowrap text-muted-foreground">
                                        {entry.age}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </>
    );
}

export function formatLatency(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    if (seconds < 60) {
        return `${seconds}s`;
    }

    const minutes = Math.floor(seconds / 60);

    return `${minutes}m ${seconds % 60}s`;
}
