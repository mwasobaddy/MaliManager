import { Head, router } from '@inertiajs/react';
import { Printer, Sparkles, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    destroy as destroyInspection,
    report as reportRoute,
} from '@/routes/tenant/inspections';

type Area = { area: string; condition: string; issues: string };

type Props = {
    propertySlug: string;
    inspection: {
        id: number;
        title: string;
        unit_name: string | null;
        inspection_date: string | null;
        notes: string | null;
        ai_report: {
            overall_condition: string;
            areas: Area[];
            recommendations: string[];
        } | null;
        report_generated_at: string | null;
        photos: { url: string; name: string }[];
    };
};

const conditionStyles: Record<string, string> = {
    excellent:
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    good: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    fair: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    poor: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};

export default function InspectionShow({ propertySlug, inspection }: Props) {
    const generate = () =>
        router.post(
            reportRoute({ property: propertySlug, inspection: inspection.id })
                .url,
            {},
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={inspection.title} />
            <div className="mx-auto max-w-4xl p-8 print:p-0">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-2 print:hidden">
                    <Heading variant="small" title={inspection.title} />
                    <div className="flex gap-2">
                        {!inspection.report_generated_at && (
                            <Button
                                onClick={generate}
                                data-test="generate-report-button"
                            >
                                <Sparkles className="size-4" />
                                Generate AI report
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            onClick={() => window.print()}
                        >
                            <Printer className="size-4" />
                            Print / PDF
                        </Button>
                        <Button
                            variant="ghost"
                            className="text-red-600 print:hidden"
                            onClick={() =>
                                confirm('Delete this inspection?') &&
                                router.delete(
                                    destroyInspection({
                                        property: propertySlug,
                                        inspection: inspection.id,
                                    }).url,
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Trash2 className="size-4" />
                            Delete
                        </Button>
                    </div>
                </div>

                <header className="mb-6 border-b pb-3">
                    <h2 className="text-xl font-semibold capitalize">
                        {inspection.unit_name} inspection
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {inspection.inspection_date}
                    </p>
                    {inspection.notes && (
                        <p className="mt-2 text-sm">
                            Inspector notes: {inspection.notes}
                        </p>
                    )}
                </header>

                {inspection.photos.length > 0 && (
                    <section className="mb-6">
                        <h3 className="mb-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            Photos
                        </h3>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 print:grid-cols-3">
                            {inspection.photos.map((photo) => (
                                <img
                                    key={photo.url}
                                    src={photo.url}
                                    alt={photo.name}
                                    className="aspect-video w-full rounded-md object-cover"
                                />
                            ))}
                        </div>
                    </section>
                )}

                {inspection.ai_report ? (
                    <section className="space-y-5">
                        <div>
                            <h3 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                Overall condition
                            </h3>
                            <span
                                className={`mt-1 inline-block rounded px-2 py-0.5 text-sm font-medium capitalize ${
                                    conditionStyles[
                                        inspection.ai_report.overall_condition
                                    ] ?? ''
                                }`}
                            >
                                {inspection.ai_report.overall_condition}
                            </span>
                        </div>

                        <div>
                            <h3 className="mb-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                Areas
                            </h3>
                            <div className="space-y-3">
                                {(inspection.ai_report.areas ?? []).map(
                                    (area, index) => (
                                        <div
                                            key={index}
                                            className="break-inside-avoid rounded-lg border border-input p-3"
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="font-medium capitalize">
                                                    {area.area}
                                                </span>
                                                <span
                                                    className={`rounded px-1.5 py-0.5 text-xs capitalize ${conditionStyles[area.condition] ?? ''}`}
                                                >
                                                    {area.condition}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {area.issues}
                                            </p>
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>

                        {(inspection.ai_report.recommendations?.length ?? 0) >
                            0 && (
                            <div>
                                <h3 className="mb-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                    Recommendations
                                </h3>
                                <ul className="list-disc space-y-1 pl-5 text-sm">
                                    {inspection.ai_report.recommendations.map(
                                        (recommendation, index) => (
                                            <li key={index}>
                                                {recommendation}
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </div>
                        )}
                    </section>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No AI report generated yet
                        {inspection.photos.length === 0
                            ? ' — add photos first.'
                            : '.'}
                    </p>
                )}
            </div>
        </>
    );
}
