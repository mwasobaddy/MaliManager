import { Head, Link, usePage } from '@inertiajs/react';
import { FileSearch, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { BulkDeleteBar } from '@/components/bulk-delete-bar';
import ConfirmDeleteOverlay from '@/components/confirm-delete-overlay';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Empty } from '@/components/ui/empty';
import { create as createInspectionRoute } from '@/routes/tenant/inspections';
import { index as propertiesIndex } from '@/routes/tenant/properties';
import { bulk as trashBulk } from '@/routes/tenant/trash';

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
    const { context } = usePage().props;
    const canDelete = (context?.permissions ?? []).includes(
        'inspection.manage',
    );

    const [bulkOpen, setBulkOpen] = useState(false);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const pageIds = inspections.map((inspection) => inspection.id);
    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id));

    // Selection never outlives the page: a refetch that drops a row also
    // drops its tick, so the bulk payload can only name visible rows.
    useEffect(() => {
        setSelectedIds((current) =>
            current.filter((id) =>
                inspections.some((inspection) => inspection.id === id),
            ),
        );
    }, [inspections]);

    return (
        <>
            <Head title="Inspections" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        level="h1"
                        title="Inspections"
                        description="Unit walkthroughs with photo-based AI condition reports."
                    />
                    <LabelledIconButton
                        icon={<Plus />}
                        label="New inspection"
                        tooltip="Start a unit walkthrough"
                        color="primary"
                        href={
                            createInspectionRoute({
                                property: propertySlug,
                            }).url
                        }
                        data-test="new-inspection-button"
                        className="max-w-20 sm:max-w-24"
                    />
                </div>

                <BulkDeleteBar
                    pageIds={pageIds}
                    selectedIds={selectedIds}
                    onSelectionChange={setSelectedIds}
                    onDelete={() => setBulkOpen(true)}
                    onClear={() => setSelectedIds([])}
                    canDelete={canDelete}
                    noun="inspections"
                />

                {inspections.length === 0 ? (
                    <Empty
                        icon={<FileSearch className="size-4" />}
                        title="No inspections yet"
                        description="Start a walkthrough to generate a photo-based condition report."
                    />
                ) : (
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-xs tracking-wide text-muted-foreground uppercase">
                                {canDelete && (
                                    <th className="w-8 px-3 py-2">
                                        <Checkbox
                                            checked={
                                                allSelected
                                                    ? true
                                                    : selectedIds.length === 0
                                                      ? false
                                                      : 'indeterminate'
                                            }
                                            onCheckedChange={() =>
                                                setSelectedIds(
                                                    allSelected
                                                        ? []
                                                        : [...pageIds],
                                                )
                                            }
                                            aria-label="Select all inspections on this page"
                                        />
                                    </th>
                                )}
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
                                    className={
                                        selectedIds.includes(inspection.id)
                                            ? 'border-b bg-muted/60 last:border-0'
                                            : 'border-b last:border-0'
                                    }
                                >
                                    {canDelete && (
                                        <td className="px-3 py-2">
                                            <Checkbox
                                                checked={selectedIds.includes(
                                                    inspection.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    setSelectedIds((current) =>
                                                        checked === true
                                                            ? [
                                                                  ...current,
                                                                  inspection.id,
                                                              ]
                                                            : current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      inspection.id,
                                                              ),
                                                    )
                                                }
                                                aria-label={`Select inspection ${inspection.title}`}
                                            />
                                        </td>
                                    )}
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

                {canDelete && bulkOpen && selectedIds.length > 0 && (
                    <ConfirmDeleteOverlay
                        open
                        onOpenChange={(open) => {
                            if (!open) {
                                setBulkOpen(false);
                            }
                        }}
                        action={trashBulk().url}
                        payload={{ module: 'inspections', ids: selectedIds }}
                        title={
                            selectedIds.length === 1
                                ? 'Delete this inspection?'
                                : `Delete ${selectedIds.length} inspections?`
                        }
                        description="They move to trash, where they can be restored or permanently deleted."
                        submitLabel={
                            selectedIds.length === 1
                                ? 'Delete inspection'
                                : `Delete ${selectedIds.length} inspections`
                        }
                        processingLabel="Deleting…"
                        onSuccess={() => {
                            setSelectedIds([]);
                            setBulkOpen(false);
                        }}
                    />
                )}
            </div>
        </>
    );
}

InspectionsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: propertiesIndex().url,
        },
        {
            title: 'Inspections',
        },
    ],
};
