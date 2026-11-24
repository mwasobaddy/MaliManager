import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileText, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format-money';
import { index as occupantsIndex } from '@/routes/tenant/occupants';

type Terms = {
    landlord: string | null;
    property: string | null;
    unit: string | null;
    rent_amount: string | null;
    rent_frequency: string | null;
    currency: string | null;
    deposit: string | null;
    starts_at: string | null;
    ends_at: string | null;
    printed_at: string;
};

type Props = {
    agreementHtml: string;
    documentUrl: string | null;
    occupantName: string;
    propertySlug: string;
    occupantId: number;
    terms: Terms;
};

const frequencyLabel: Record<string, string> = {
    daily: 'per day',
    weekly: 'per week',
    monthly: 'per month',
    yearly: 'per year',
};

/**
 * A printable lease agreement: a light paper sheet in both themes (ink
 * stays readable and cheap), with the key terms up top, the authored
 * agreement text in the middle, and signature lines at the foot. The
 * screen-only bar carries a back link and the print action.
 */
export default function OccupantAgreement({
    agreementHtml,
    documentUrl,
    occupantName,
    propertySlug,
    occupantId,
    terms,
}: Props) {
    const details: [string, string][] = [
        ['Landlord', terms.landlord ?? '—'],
        ['Tenant', occupantName || '—'],
        ['Property', terms.property ?? '—'],
        ['Unit', terms.unit ?? '—'],
        [
            'Rent',
            terms.rent_amount !== null
                ? `${formatMoney(terms.rent_amount, terms.currency)} ${frequencyLabel[terms.rent_frequency ?? ''] ?? ''}`.trim()
                : '—',
        ],
        [
            'Deposit',
            terms.deposit !== null
                ? formatMoney(terms.deposit, terms.currency)
                : 'None',
        ],
        ['Starts', terms.starts_at ?? '—'],
        ['Ends', terms.ends_at ?? 'Open-ended'],
    ];

    return (
        <>
            <Head title={`Lease agreement — ${occupantName}`} />

            <div className="min-h-screen bg-muted/40 print:bg-white">
                <div className="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-4 sm:px-8 print:hidden">
                    <Button type="button" variant="outline" size="sm" asChild>
                        <Link
                            href={occupantsIndex.url(
                                { property: propertySlug },
                                {
                                    query: {
                                        mode: 'view',
                                        occupant: String(occupantId),
                                    },
                                },
                            )}
                        >
                            <ArrowLeft className="size-4" />
                            Back to occupant
                        </Link>
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => window.print()}
                        data-test="print-agreement-button"
                    >
                        <Printer className="size-4" />
                        Print / Save as PDF
                    </Button>
                </div>

                <main className="mx-auto max-w-3xl px-4 pb-12 sm:px-8 print:max-w-none print:p-0 print:pb-0">
                    <div className="rounded-xl bg-white p-8 text-neutral-900 shadow-lg sm:p-12 print:rounded-none print:p-0 print:shadow-none">
                        <header className="border-b-2 border-neutral-900 pb-6">
                            <p className="text-xs font-semibold tracking-[0.2em] text-neutral-500 uppercase">
                                Residential tenancy agreement
                            </p>
                            <h1 className="mt-2 text-2xl font-bold">
                                {terms.property ?? 'Lease agreement'}
                                {terms.unit ? ` — Unit ${terms.unit}` : ''}
                            </h1>
                            <p className="mt-2 text-sm text-neutral-500">
                                Between {terms.landlord ?? 'the landlord'} and{' '}
                                {occupantName || 'the tenant'} · Printed{' '}
                                {terms.printed_at}
                            </p>
                        </header>

                        <section aria-label="Key terms" className="py-6">
                            <dl className="grid gap-x-8 gap-y-3 sm:grid-cols-2">
                                {details.map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="grid grid-cols-[7rem_1fr] gap-2 border-b border-neutral-100 pb-2 text-sm"
                                    >
                                        <dt className="text-neutral-500">
                                            {label}
                                        </dt>
                                        <dd className="font-medium break-words">
                                            {value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>

                        {agreementHtml ? (
                            <article className="prose prose-sm max-w-none py-2 text-neutral-900 prose-headings:text-neutral-900 prose-a:text-neutral-900">
                                <span
                                    // Sanitized server-side on save; placeholders merged from lease data.
                                    dangerouslySetInnerHTML={{
                                        __html: agreementHtml,
                                    }}
                                />
                            </article>
                        ) : (
                            <p className="py-2 text-sm text-neutral-500 italic">
                                No written agreement for this lease — the key
                                terms above stand as the record.
                            </p>
                        )}

                        <section
                            aria-label="Signatures"
                            className="mt-10 grid gap-8 sm:grid-cols-2"
                        >
                            {['Landlord', 'Tenant'].map((party) => (
                                <div key={party}>
                                    <div className="h-12 border-b border-neutral-400" />
                                    <p className="mt-2 text-sm font-medium">
                                        {party === 'Tenant'
                                            ? (occupantName ?? 'Tenant')
                                            : (terms.landlord ?? 'Landlord')}
                                    </p>
                                    <p className="text-xs text-neutral-500">
                                        {party} · signature &amp; date
                                    </p>
                                </div>
                            ))}
                        </section>

                        {documentUrl && (
                            <p className="mt-8 border-t border-neutral-200 pt-4 text-sm text-neutral-500 print:hidden">
                                An uploaded agreement document is also attached:{' '}
                                <a
                                    href={documentUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 font-medium text-neutral-900 underline underline-offset-4"
                                >
                                    <FileText className="size-4" />
                                    Open document
                                </a>
                            </p>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}
