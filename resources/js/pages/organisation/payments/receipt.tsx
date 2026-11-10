import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { formatMoney } from '@/lib/format-money';

type Props = {
    tenantName: string;
    propertyName: string | null;
    unitName: string | null;
    amount: number;
    currency: string;
    paidOn: string | null;
    periodStart: string | null;
    periodEnd: string | null;
    method: string;
    reference: string | null;
    status: string;
    notes: string | null;
};

/**
 * A chrome-free printable view of a rent receipt. Open it and use
 * "Print" (or Ctrl/Cmd+P) to save as PDF via the browser's print dialog.
 */
export default function PaymentReceipt({
    tenantName,
    propertyName,
    unitName,
    amount,
    currency,
    periodStart,
    periodEnd,
    method,
    reference,
    status,
    notes,
}: Props) {
    const methodLabel = method
        .split('-')
        .map((part: string) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');

    const statusColor =
        status === 'received'
            ? 'bg-status-success-bg text-status-success-fg'
            : status === 'pending'
              ? 'bg-status-warning-bg text-status-warning-fg'
              : 'bg-status-danger-bg text-status-danger-fg';

    return (
        <>
            <Head title={`Receipt — ${tenantName}`} />
            <div className="mx-auto max-w-3xl p-8 print:p-0">
                <div className="mb-6 flex items-center justify-between print:hidden">
                    <h1 className="text-lg font-semibold">
                        Rent receipt — {tenantName}
                    </h1>
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        data-test="print-receipt-button"
                    >
                        <Printer className="size-4" />
                        Print / Save as PDF
                    </button>
                </div>

                <article className="prose prose-sm max-w-none text-foreground print:text-black">
                    <header className="mb-8">
                        <div className="mb-4 grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Tenant
                                </p>
                                <p className="font-medium">{tenantName}</p>
                            </div>
                            {propertyName && (
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Property
                                    </p>
                                    <p className="font-medium">
                                        {propertyName}
                                    </p>
                                </div>
                            )}
                            {unitName && (
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Unit
                                    </p>
                                    <p className="font-medium">{unitName}</p>
                                </div>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Amount
                                </p>
                                <p className="text-2xl font-medium">
                                    {formatMoney(amount, currency)}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Status
                                </p>
                                <p className={`font-medium ${statusColor}`}>
                                    {status}
                                </p>
                            </div>
                        </div>

                        {(periodStart || periodEnd) && (
                            <div className="mt-4 border-t pt-4">
                                <p className="text-sm text-muted-foreground">
                                    Rent period
                                </p>
                                <p className="font-medium">
                                    {periodStart ? periodStart : ''}
                                    {periodStart && periodEnd ? ' - ' : ''}
                                    {periodEnd || ''}
                                </p>
                            </div>
                        )}

                        {reference && (
                            <div className="mt-4 border-t pt-4">
                                <p className="text-sm text-muted-foreground">
                                    Reference
                                </p>
                                <p className="font-medium">{reference}</p>
                            </div>
                        )}

                        {method && (
                            <div className="mt-4 border-t pt-4">
                                <p className="text-sm text-muted-foreground">
                                    Payment method
                                </p>
                                <p className="font-medium">{methodLabel}</p>
                            </div>
                        )}

                        {notes && (
                            <div className="mt-4 border-t pt-4">
                                <p className="text-sm text-muted-foreground">
                                    Notes
                                </p>
                                <p>{notes}</p>
                            </div>
                        )}
                    </header>
                </article>
            </div>
        </>
    );
}
