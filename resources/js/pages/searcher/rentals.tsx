import { Head } from '@inertiajs/react';
import { Building2, CalendarRange, History, Home } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';

type Lease = {
    id: number;
    property_name: string | null;
    unit_name: string | null;
    organization_name: string | null;
    starts_at: string | null;
    ends_at: string | null;
    rent_amount: number | null;
    rent_frequency: string | null;
    currency: string | null;
    deposit: number | null;
    agreement_text: string | null;
    status: string;
    duration_in_days: number | null;
    total_cost: number | null;
    is_active: boolean;
};

type Props = {
    leases: Lease[];
};

const frequencyLabel: Record<string, string> = {
    daily: 'per day',
    weekly: 'per week',
    monthly: 'per month',
    yearly: 'per year',
};

function formatMoney(amount: number | null, currency: string | null): string {
    if (amount === null) {
        return '—';
    }

    const code = currency ?? '';

    try {
        return new Intl.NumberFormat('en-KE', {
            style: 'currency',
            currency: code || 'KES',
        }).format(amount);
    } catch {
        return `${code} ${amount.toLocaleString()}`;
    }
}

export default function SearcherRentals({ leases }: Props) {
    const [tab, setTab] = useState<'current' | 'history'>('current');

    const current = leases.filter((lease) => lease.is_active);
    const history = leases.filter((lease) => !lease.is_active);
    const visible = tab === 'current' ? current : history;

    return (
        <>
            <Head title="My rentals" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="My rentals"
                        description="Your rental history across every organization you have rented from."
                    />
                    <div className="flex items-center gap-2">
                        <Button
                            variant={tab === 'current' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setTab('current')}
                        >
                            <Home className="size-4" />
                            Current ({current.length})
                        </Button>
                        <Button
                            variant={tab === 'history' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setTab('history')}
                        >
                            <History className="size-4" />
                            History ({history.length})
                        </Button>
                    </div>
                </div>

                {visible.length === 0 ? (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <Building2 className="mx-auto size-10 text-muted-foreground" />
                            <p className="mt-4 font-medium">
                                {tab === 'current'
                                    ? 'You have no active rentals'
                                    : 'No past rentals yet'}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                When an organization adds you as a renter, your lease will appear here.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {visible.map((lease) => (
                            <Card key={lease.id}>
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <CardTitle className="text-base">
                                                {lease.unit_name ?? 'Unit'}
                                                {lease.property_name ? (
                                                    <span className="font-normal text-muted-foreground">
                                                        {' '}
                                                        · {lease.property_name}
                                                    </span>
                                                ) : null}
                                            </CardTitle>
                                            <CardDescription>
                                                {lease.organization_name ?? 'Organization'}
                                            </CardDescription>
                                        </div>
                                        <Badge variant={lease.is_active ? 'default' : 'secondary'}>
                                            {lease.is_active ? 'Active' : 'Ended'}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm">
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <CalendarRange className="size-4" />
                                        <span>
                                            {lease.starts_at ?? '—'}
                                            {' → '}
                                            {lease.ends_at ?? 'present'}
                                        </span>
                                        {lease.duration_in_days !== null ? (
                                            <span className="ml-auto">
                                                {lease.duration_in_days} days
                                            </span>
                                        ) : null}
                                    </div>

                                    <Separator />

                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <p className="text-muted-foreground">Rent</p>
                                            <p className="font-medium">
                                                {formatMoney(lease.rent_amount, lease.currency)}
                                                {lease.rent_frequency
                                                    ? ` ${frequencyLabel[lease.rent_frequency] ?? ''}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground">Deposit</p>
                                            <p className="font-medium">
                                                {formatMoney(lease.deposit, lease.currency)}
                                            </p>
                                        </div>
                                        <div className="col-span-2">
                                            <p className="text-muted-foreground">
                                                Total cost (rent + deposit)
                                            </p>
                                            <p className="font-medium">
                                                {formatMoney(lease.total_cost, lease.currency)}
                                            </p>
                                        </div>
                                    </div>

                                    {lease.agreement_text ? (
                                        <>
                                            <Separator />
                                            <div>
                                                <p className="mb-1 text-muted-foreground">
                                                    Agreement
                                                </p>
                                                <p className="whitespace-pre-wrap text-xs">
                                                    {lease.agreement_text}
                                                </p>
                                            </div>
                                        </>
                                    ) : null}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
