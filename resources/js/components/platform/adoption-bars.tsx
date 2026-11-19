import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { Adoption } from './types';

export function AdoptionBars({ features }: { features: Adoption['features'] }) {
    const max = Math.max(...features.map((f) => f.organizations), 1);

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Feature adoption
                </CardTitle>
                <CardDescription>
                    Share of active organizations with real data in each module.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {features.map((feature) => (
                    <div key={feature.key} className="flex flex-col gap-1">
                        <div className="flex items-baseline justify-between gap-2 text-sm">
                            <span className="font-medium">{feature.label}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {feature.organizations} · {feature.percent}%
                            </span>
                        </div>
                        <div
                            className="h-2.5 overflow-hidden rounded-full bg-muted"
                            role="img"
                            aria-label={`${feature.label}: ${feature.organizations} organizations, ${feature.percent}%`}
                        >
                            <div
                                className="h-full rounded-full bg-chart-2"
                                style={{
                                    width: `${(feature.organizations / max) * 100}%`,
                                }}
                            />
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
