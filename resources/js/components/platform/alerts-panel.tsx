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
 * trains the reader to dismiss it like marketing.
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
                            <span>{alert.text}</span>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
