import { ChevronDown, ChevronUp } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

type StatItem = {
    title: string;
    value: number | string;
    description?: string;
    icon: ReactNode;
};

type StatsCardsProps = {
    label: string;
    summary?: string;
    items: StatItem[];
};

export function StatsCards({ label, summary, items }: StatsCardsProps) {
    return (
        <Collapsible className="group/collapsible" defaultOpen>
            <CollapsibleTrigger asChild>
                <button className="flex w-full items-center gap-2 rounded-lg p-2 text-sm font-medium text-muted-foreground hover:bg-muted/50 hover:text-foreground">
                    {label}
                    {summary && (
                        <span className="ml-auto text-xs text-muted-foreground tabular-nums">
                            {summary}
                        </span>
                    )}
                    <ChevronDown className="h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:hidden" />
                    <ChevronUp className="h-4 w-4 transition-transform duration-200 group-data-[state=closed]/collapsible:hidden" />
                </button>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div className="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    {items.map((item) => (
                        <Card key={item.title}>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {item.title}
                                </CardTitle>
                                <div className="rounded-full bg-muted p-2 [&_svg]:h-4 [&_svg]:w-4">
                                    {item.icon}
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="text-xl font-bold lg:text-2xl">
                                    {item.value}
                                </div>
                                {item.description && (
                                    <p className="hidden text-xs text-muted-foreground lg:block">
                                        {item.description}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
