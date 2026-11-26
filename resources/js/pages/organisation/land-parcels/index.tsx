import { Head, Link, router, usePage } from '@inertiajs/react';
import { Map, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { BulkDeleteBar } from '@/components/bulk-delete-bar';
import ConfirmDeleteOverlay from '@/components/confirm-delete-overlay';
import Heading from '@/components/heading';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, show } from '@/routes/tenant/land-parcels';
import { bulk as trashBulk } from '@/routes/tenant/trash';

type Organization = {
    id: number;
    name: string;
    slug: string;
};

type Branch = {
    id: number;
    name: string;
};

type LandParcel = {
    id: number;
    name: string;
    slug: string;
    city: string | null;
    status: string;
    zoning: string;
    branch: { id: number; name: string } | null;
};

type Props = {
    organization: Organization;
    land_parcels: LandParcel[];
    branches: Branch[];
    filters: { branch: number | null };
    canCreateLandParcel: boolean;
};

export default function LandParcelsIndex({
    organization,
    land_parcels,
    branches,
    filters,
    canCreateLandParcel,
}: Props) {
    const { context } = usePage().props;
    const canDelete = (context?.permissions ?? []).includes(
        'land_parcel.delete',
    );
    const pageUrl = usePage().url.split('?')[0];

    function filterByBranch(value: string) {
        router.get(
            pageUrl,
            { branch: value === 'all' ? '' : value },
            { preserveState: true, only: ['land_parcels', 'filters'] },
        );
    }

    const [bulkOpen, setBulkOpen] = useState(false);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);

    // Selection never outlives the page: a refetch that drops a card also
    // drops its tick, so the bulk payload can only name visible parcels.
    useEffect(() => {
        setSelectedIds((current) =>
            current.filter((id) =>
                land_parcels.some((parcel) => parcel.id === id),
            ),
        );
    }, [land_parcels]);

    return (
        <>
            <Head title="Land parcels" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        className="min-w-0 flex-1"
                        level="h1"
                        variant="small"
                        title={`${organization.name} land parcels`}
                        description="Select a parcel to manage its details, or add a new one."
                    />
                    {canCreateLandParcel && (
                        <LabelledIconButton
                            icon={<Plus />}
                            label="Add land parcel"
                            tooltip="Register a parcel of land"
                            color="primary"
                            href={create().url}
                            className="ml-auto max-w-20 sm:max-w-24"
                        />
                    )}
                </div>

                {branches.length > 1 ? (
                    <div className="flex items-center gap-2">
                        <Select
                            value={
                                filters.branch ? String(filters.branch) : 'all'
                            }
                            onValueChange={filterByBranch}
                        >
                            <SelectTrigger
                                aria-label="Filter by branch"
                                className="w-48"
                            >
                                <SelectValue placeholder="All branches" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All branches
                                </SelectItem>
                                {branches.map((branch) => (
                                    <SelectItem
                                        key={branch.id}
                                        value={String(branch.id)}
                                    >
                                        {branch.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                ) : null}

                {land_parcels.length === 0 ? (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <Map className="mx-auto size-10 text-muted-foreground" />
                            <p className="mt-4 font-medium">
                                No land parcels yet
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Add your first parcel to start tracking plots of
                                land.
                            </p>
                            {canCreateLandParcel && (
                                <Button asChild className="mt-6">
                                    <Link href={create()}>
                                        Add your first land parcel
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <BulkDeleteBar
                            pageIds={land_parcels.map((parcel) => parcel.id)}
                            selectedIds={selectedIds}
                            onSelectionChange={setSelectedIds}
                            onDelete={() => setBulkOpen(true)}
                            onClear={() => setSelectedIds([])}
                            canDelete={canDelete}
                            showSelectAll
                            noun="land parcels"
                        />
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {land_parcels.map((parcel) => (
                                <Link
                                    key={parcel.id}
                                    href={show(parcel.slug)}
                                    className={
                                        selectedIds.includes(parcel.id)
                                            ? 'rounded-xl border border-input bg-muted/60 transition-colors hover:bg-muted/60'
                                            : 'rounded-xl border border-input bg-card transition-colors hover:bg-muted'
                                    }
                                >
                                    <Card className="border-0 bg-transparent shadow-none">
                                        <CardHeader>
                                            <div className="flex items-center gap-2">
                                                {canDelete && (
                                                    <span
                                                        onClick={(e) =>
                                                            e.stopPropagation()
                                                        }
                                                        onKeyDown={(e) =>
                                                            e.stopPropagation()
                                                        }
                                                    >
                                                        <Checkbox
                                                            checked={selectedIds.includes(
                                                                parcel.id,
                                                            )}
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                setSelectedIds(
                                                                    (
                                                                        current,
                                                                    ) =>
                                                                        checked ===
                                                                        true
                                                                            ? [
                                                                                  ...current,
                                                                                  parcel.id,
                                                                              ]
                                                                            : current.filter(
                                                                                  (
                                                                                      id,
                                                                                  ) =>
                                                                                      id !==
                                                                                      parcel.id,
                                                                              ),
                                                                )
                                                            }
                                                            aria-label={`Select parcel ${parcel.name}`}
                                                        />
                                                    </span>
                                                )}
                                                <Map className="size-4 text-muted-foreground" />
                                                <CardTitle>
                                                    {parcel.name}
                                                </CardTitle>
                                            </div>
                                            <CardDescription>
                                                {parcel.city
                                                    ? `${parcel.city} · `
                                                    : ''}
                                                {parcel.zoning}
                                                {parcel.branch
                                                    ? ` · ${parcel.branch.name}`
                                                    : null}
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <Badge
                                                variant={
                                                    parcel.status === 'active'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {parcel.status}
                                            </Badge>
                                        </CardContent>
                                    </Card>
                                </Link>
                            ))}
                        </div>

                        {canDelete && bulkOpen && selectedIds.length > 0 && (
                            <ConfirmDeleteOverlay
                                open
                                onOpenChange={(open) => {
                                    if (!open) {
                                        setBulkOpen(false);
                                    }
                                }}
                                action={trashBulk().url}
                                payload={{
                                    module: 'land-parcels',
                                    ids: selectedIds,
                                }}
                                title={
                                    selectedIds.length === 1
                                        ? 'Delete this land parcel?'
                                        : `Delete ${selectedIds.length} land parcels?`
                                }
                                description="They move to trash, where they can be restored or permanently deleted."
                                submitLabel={
                                    selectedIds.length === 1
                                        ? 'Delete land parcel'
                                        : `Delete ${selectedIds.length} land parcels`
                                }
                                processingLabel="Deleting…"
                                onSuccess={() => {
                                    setSelectedIds([]);
                                    setBulkOpen(false);
                                }}
                            />
                        )}
                    </>
                )}
            </div>
        </>
    );
}

LandParcelsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Land parcels',
        },
    ],
};
