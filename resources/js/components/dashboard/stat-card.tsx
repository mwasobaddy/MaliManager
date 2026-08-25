import { Card, CardContent } from '@/components/ui/card';

type Props = {
    label: string;
    value: string | number;
    hint?: string;
};

export function StatCard({ label, value, hint }: Props) {
    return (
        <Card>
            <CardContent className="p-4">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p className="mt-1 text-2xl font-semibold">{value}</p>
                {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
            </CardContent>
        </Card>
    );
}
