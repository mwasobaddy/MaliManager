import { Link } from '@inertiajs/react';
import { StatusIcon } from '@/components/status-cell';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { OverviewAlert } from './types';

/**
 * Deliberately boring: flat rows with a status glyph and plain text. An
 * alert that shares the promo-card language (gradient, glow, illustration)
 * trains the reader to dismiss it like marketing. Actionable rows linkify
 * only the identifying noun — a full row of buttons would turn a
 * scan-friendly list into competing CTAs.
 */
export function AlertsPanel({ alerts }: { alerts: OverviewAlert[] }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Needs attention
                </CardTitle>
                <CardDescription>
                    Plain text on purpose — actionable, not decorative.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ul className="divide-y divide-border">
                    {alerts.map((alert) => (
                        <li
                            key={alert.text}
                            className="flex items-start gap-2.5 py-2.5 text-sm first:pt-0 last:pb-0"
                        >
                            <StatusIcon
                                tone={alert.severity}
                                label={alert.severity}
                                className="mt-0.5 shrink-0"
                            />
                            <span>
                                <AlertText alert={alert} />
                            </span>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

/**
 * Splits `text` on the first occurrence of `linkText` so only the noun
 * carries the link. Falls back to plain text when the label is absent —
 * platform alerts have no href and render exactly as before.
 */
function AlertText({ alert }: { alert: OverviewAlert }) {
    if (
        !alert.href ||
        !alert.linkText ||
        !alert.text.includes(alert.linkText)
    ) {
        return <>{alert.text}</>;
    }

    const [before, ...rest] = alert.text.split(alert.linkText);
    const after = rest.join(alert.linkText);

    return (
        <>
            {before}
            <Link
                href={alert.href}
                className="font-medium underline decoration-muted-foreground underline-offset-2 hover:decoration-foreground"
            >
                {alert.linkText}
            </Link>
            {after}
        </>
    );
}
