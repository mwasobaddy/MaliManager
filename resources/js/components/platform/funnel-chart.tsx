import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { Growth } from './types';

/**
 * Trial funnel as horizontal bars with the stage-to-stage conversion in
 * text. One brand-neutral series: the drop-off numbers are the story, and
 * a rainbow would imply categories that are not there.
 */
export function FunnelChart({ stages }: { stages: Growth['funnel'] }) {
    const first = stages[0]?.count ?? 0;

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Trial funnel
                </CardTitle>
                <CardDescription>
                    Current state per stage — a deleted property stops counting.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {stages.map((stage) => (
                    <div key={stage.stage} className="flex flex-col gap-1">
                        <div className="flex items-baseline justify-between gap-2 text-sm">
                            <span className="font-medium">{stage.label}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {stage.count}
                                {stage.conversion !== null &&
                                    ` · ${stage.conversion}% carry-over`}
                            </span>
                        </div>
                        <div
                            className="h-2.5 overflow-hidden rounded-full bg-muted"
                            role="img"
                            aria-label={`${stage.label}: ${stage.count} organizations${stage.conversion !== null ? `, ${stage.conversion}% carry-over` : ''}`}
                        >
                            <div
                                className="h-full rounded-full bg-chart-1"
                                style={{
                                    width: `${first > 0 ? Math.max((stage.count / first) * 100, stage.count > 0 ? 2 : 0) : 0}%`,
                                }}
                            />
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
