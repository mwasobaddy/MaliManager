import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';

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
    paidOn,
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

    const statusColor = status === 'received'
        ? 'bg-green-100 text-green-800'
        : status === 'pending'
            ? 'bg-yellow-100 text-yellow-800'
            : 'bg-red-100 text-red-800';

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

                <article className="prose prose-sm max-w-none text-black spacing-js">
                    <header className="mb-8">
                        <div className="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <p className="text-sm text-muted-foreground">Tenant</p>
                                <p className="font-medium">{tenantName}</p>
                            </div>
                            {propertyName && (
                                <div>
                                    <p className="text-sm text-muted-foreground">Property</p>
                                    <p className="font-medium">{propertyName}</p>
                                </div>
                            )}
                            {unitName && (
                                <div>
                                    <p className="text-sm text-muted-foreground">Unit</p>
                                    <p className="font-medium">{unitName}</p>
                                </div>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-sm text-muted-foreground">Amount</p>
                                <p className="font-medium text-2xl">{amount} {currency}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Status</p>
                                <p className={`font-medium ${statusColor}`}>
                                    {status}
                                </p>
                            </div>
                        </div>

                        {periodStart || periodEnd && (
                            <div className="mt-4 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">Rent period</p>
                                <p className="font-medium">
                                    {periodStart ? periodStart : ''}
                                    {periodStart && periodEnd ? ' - ' : ''}
                                    {periodEnd || ''}
                                </p>
                            </div>
                        )}

                        {reference && (
                            <div className="mt-4 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">Reference</p>
                                <p className="font-medium">{reference}</p>
                            </div>
                        )}

                        {method && (
                            <div className="mt-4 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">Payment method</p>
                                <p className="font-medium">{methodLabel}</p>
                            </div>
                        )}

                        {notes && (
                            <div className="mt-4 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">Notes</p>
                                <p>{notes}</p>
                            </div>
                        )}
                    </header>
                </article>
            </div>
        </>
    );
}