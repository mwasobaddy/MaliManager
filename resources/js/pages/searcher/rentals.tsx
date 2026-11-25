import { Head } from '@inertiajs/react';
import { Building2, CalendarRange, History, Home } from 'lucide-react';
import { useState } from 'react';
import { Form } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { formatMoney } from '@/lib/format-money';
import {
    destroy as destroyMoveOutNotice,
    store as storeMoveOutNotice,
} from '@/routes/searcher/move-out-notices';

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
    outstanding: string;
    is_overdue: boolean;
    move_out_notice: {
        id: number;
        move_out_date: string;
        note: string | null;
    } | null;
    payment_details: {
        paybill_number: string | null;
        account_reference: string | null;
        till_number: string | null;
        notes: string | null;
    } | null;
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
                        level="h1"
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
                                When an organization adds you as a renter, your
                                lease will appear here.
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
                                                {lease.organization_name ??
                                                    'Organization'}
                                            </CardDescription>
                                        </div>
                                        <Badge
                                            variant={
                                                lease.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {lease.is_active
                                                ? 'Active'
                                                : 'Ended'}
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
                                            <p className="text-muted-foreground">
                                                Rent
                                            </p>
                                            <p className="font-medium">
                                                {formatMoney(
                                                    lease.rent_amount,
                                                    lease.currency,
                                                )}
                                                {lease.rent_frequency
                                                    ? ` ${frequencyLabel[lease.rent_frequency] ?? ''}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground">
                                                Deposit
                                            </p>
                                            <p className="font-medium">
                                                {formatMoney(
                                                    lease.deposit,
                                                    lease.currency,
                                                )}
                                            </p>
                                        </div>
                                        <div className="col-span-2">
                                            <p className="text-muted-foreground">
                                                Total cost (rent + deposit)
                                            </p>
                                            <p className="font-medium">
                                                {formatMoney(
                                                    lease.total_cost,
                                                    lease.currency,
                                                )}
                                            </p>
                                        </div>
                                        {lease.is_active && (
                                            <div className="col-span-2">
                                                <p className="text-muted-foreground">
                                                    Balance due
                                                </p>
                                                <p className="font-medium tabular-nums">
                                                    {lease.outstanding !==
                                                    '0.00' ? (
                                                        <span className="text-status-danger">
                                                            {formatMoney(
                                                                Number(
                                                                    lease.outstanding,
                                                                ),
                                                                lease.currency,
                                                            )}
                                                        </span>
                                                    ) : (
                                                        'Paid up'
                                                    )}
                                                </p>
                                            </div>
                                        )}
                                    </div>

                                    {lease.is_active && (
                                        <MoveOutSection lease={lease} />
                                    )}

                                    {lease.payment_details &&
                                    (lease.payment_details.paybill_number ||
                                        lease.payment_details.till_number ||
                                        lease.payment_details.notes) ? (
                                        <>
                                            <Separator />
                                            <div>
                                                <p className="mb-1 text-muted-foreground">
                                                    How to pay
                                                </p>
                                                <div className="space-y-1 text-sm">
                                                    {lease.payment_details
                                                        .paybill_number && (
                                                        <p>
                                                            M-Pesa paybill:{' '}
                                                            <span className="font-medium tabular-nums">
                                                                {
                                                                    lease
                                                                        .payment_details
                                                                        .paybill_number
                                                                }
                                                            </span>
                                                            {lease
                                                                .payment_details
                                                                .account_reference && (
                                                                <>
                                                                    {' '}
                                                                    · account{' '}
                                                                    <span className="font-medium">
                                                                        {
                                                                            lease
                                                                                .payment_details
                                                                                .account_reference
                                                                        }
                                                                    </span>
                                                                </>
                                                            )}
                                                        </p>
                                                    )}
                                                    {lease.payment_details
                                                        .till_number && (
                                                        <p>
                                                            M-Pesa till:{' '}
                                                            <span className="font-medium tabular-nums">
                                                                {
                                                                    lease
                                                                        .payment_details
                                                                        .till_number
                                                                }
                                                            </span>
                                                        </p>
                                                    )}
                                                    {lease.payment_details
                                                        .notes && (
                                                        <p className="text-muted-foreground">
                                                            {
                                                                lease
                                                                    .payment_details
                                                                    .notes
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </>
                                    ) : null}

                                    {lease.agreement_text ? (
                                        <>
                                            <Separator />
                                            <div>
                                                <p className="mb-1 text-muted-foreground">
                                                    Agreement
                                                </p>
                                                {/* Sanitized server-side on save (formatting allowlist). */}
                                                <div
                                                    className="prose prose-sm max-w-none text-xs"
                                                    dangerouslySetInnerHTML={{
                                                        __html: lease.agreement_text,
                                                    }}
                                                />
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

/**
 * Thirty days notice, given from the couch: the tenant picks a move-out
 * day (never sooner than 30 days out — the field enforces it and so does
 * the server) and the landlord sees it on their dashboard.
 */
function MoveOutSection({ lease }: { lease: Lease }) {
    const [isOpen, setIsOpen] = useState(false);
    const minDate = new Date(Date.now() + 30 * 86400000)
        .toISOString()
        .slice(0, 10);

    if (lease.move_out_notice) {
        return (
            <>
                <Separator />
                <div className="rounded-lg border border-border-strong p-3">
                    <p className="text-sm font-medium">
                        Notice given — moving out{' '}
                        {lease.move_out_notice.move_out_date}
                    </p>
                    {lease.move_out_notice.note && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {lease.move_out_notice.note}
                        </p>
                    )}
                    <Form
                        {...destroyMoveOutNotice.form(lease.move_out_notice.id)}
                        options={{ preserveScroll: true }}
                        className="mt-2"
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                size="sm"
                                variant="outline"
                                disabled={processing}
                            >
                                Withdraw notice
                            </Button>
                        )}
                    </Form>
                </div>
            </>
        );
    }

    return (
        <>
            <Separator />
            <div>
                {!isOpen ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => setIsOpen(true)}
                    >
                        Give 30-day notice
                    </Button>
                ) : (
                    <Form
                        {...storeMoveOutNotice.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setIsOpen(false)}
                        className="grid gap-2 rounded-lg border border-border-strong p-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="lease_id"
                                    value={lease.id}
                                />
                                <Label htmlFor={`move-out-${lease.id}`}>
                                    Move-out date
                                </Label>
                                <Input
                                    id={`move-out-${lease.id}`}
                                    name="move_out_date"
                                    type="date"
                                    min={minDate}
                                    defaultValue=""
                                    required
                                />
                                <InputError message={errors.move_out_date} />
                                <Label htmlFor={`move-out-note-${lease.id}`}>
                                    Note (optional)
                                </Label>
                                <Textarea
                                    id={`move-out-note-${lease.id}`}
                                    name="note"
                                    rows={2}
                                    maxLength={1000}
                                    placeholder="Forwarding address, handover notes…"
                                />
                                <InputError message={errors.note} />
                                <InputError message={errors.lease_id} />
                                <div className="flex gap-2">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={processing}
                                    >
                                        Send notice
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => setIsOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Your landlord is notified and the move-out
                                    day must be at least 30 days away.
                                </p>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}
