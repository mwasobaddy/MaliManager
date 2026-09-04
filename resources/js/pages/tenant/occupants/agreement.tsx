import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';

type Props = {
    agreementHtml: string;
    documentUrl: string | null;
    occupantName: string;
};

/**
 * A chrome-free printable view of a lease agreement. Open it and use
 * "Print" (or Ctrl/Cmd+P) to save as PDF via the browser's print dialog.
 */
export default function OccupantAgreement({
    agreementHtml,
    documentUrl,
    occupantName,
}: Props) {
    return (
        <>
            <Head title={`Lease agreement — ${occupantName}`} />
            <div className="mx-auto max-w-3xl p-8 print:p-0">
                <div className="mb-6 flex items-center justify-between print:hidden">
                    <h1 className="text-lg font-semibold">
                        Lease agreement — {occupantName}
                    </h1>
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        data-test="print-agreement-button"
                    >
                        <Printer className="size-4" />
                        Print / Save as PDF
                    </button>
                </div>

                {agreementHtml ? (
                    // Sanitized server-side on save; placeholders merged from lease data.
                    <article
                        className="prose prose-sm max-w-none text-black"
                        dangerouslySetInnerHTML={{ __html: agreementHtml }}
                    />
                ) : (
                    <p className="text-muted-foreground">
                        No written agreement for this lease.
                    </p>
                )}

                {documentUrl && (
                    <section className="mt-8 border-t pt-4 print:hidden">
                        <p className="text-sm text-muted-foreground">
                            An uploaded agreement document is also attached:{' '}
                            <a
                                href={documentUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-primary underline"
                            >
                                open document
                            </a>
                        </p>
                    </section>
                )}
            </div>
        </>
    );
}
