import { Head, Link } from '@inertiajs/react';
import { FileSearch, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create as createInspectionRoute } from '@/routes/tenant/inspections';

type InspectionRow = {
    id: number;
    title: string;
    unit_name: string | null;
    inspection_date: string | null;
    photo_count: number;
    has_report: boolean;
};

type Props = {
    propertySlug: string;
    inspections: InspectionRow[];
};

export default function InspectionsIndex({ propertySlug, inspections }: Props) {
    return (
        <>
            <Head title="Inspections" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Inspections"
                        description="Unit walkthroughs with photo-based AI condition reports."
                    />
                    <Button asChild data-test="new-inspection-button">
                        <Link
                            href={createInspectionRoute({
                                property: propertySlug,
                            })}
                        >
                            <Plus className="size-4" />
                            New inspection
                        </Link>
                    </Button>
                </div>

                {inspections.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No inspections yet.
                    </p>
                ) : (
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-xs tracking-wide text-muted-foreground uppercase">
                                <th className="px-3 py-2">Title</th>
                                <th className="px-3 py-2">Unit</th>
                                <th className="px-3 py-2">Date</th>
                                <th className="px-3 py-2">Photos</th>
                                <th className="px-3 py-2">Report</th>
                                <th className="px-3 py-2 text-right">Open</th>
                            </tr>
                        </thead>
                        <tbody>
                            {inspections.map((inspection) => (
                                <tr
                                    key={inspection.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-3 py-2 font-medium">
                                        {inspection.title}
                                    </td>
                                    <td className="px-3 py-2">
                                        {inspection.unit_name ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {inspection.inspection_date}
                                    </td>
                                    <td className="px-3 py-2">
                                        {inspection.photo_count}
                                    </td>
                                    <td className="px-3 py-2">
                                        {inspection.has_report ? (
                                            <Badge>Generated</Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                Pending
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="sm"
                                        >
                                            <Link
                                                href={`/inspections/${inspection.id}`}
                                                aria-label={`Open inspection ${inspection.title}`}
                                            >
                                                <FileSearch className="size-4" />
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </>
    );
}
