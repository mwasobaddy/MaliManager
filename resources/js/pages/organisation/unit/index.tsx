import { Head, router } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import { Building2, ImageOff, ListFilter, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import * as unitRoutes from '@/routes/tenant/units';

type UnitRow = {
    id: number;
    name: string;
    slug: string;
    type: string | null;
    status: string;
    monthly_rent: string | null;
    photos_count: number;
    thumbnail: string | null;
    property: { id: number | null; name: string | null; slug: string | null };
};

type Props = {
    units: {
        data: UnitRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    properties: { id: number; name: string }[];
};

/** Debounce so a fast typist issues one request, not one per keystroke. */
const SEARCH_DEBOUNCE_MS = 350;

const STATUSES = [
    { value: 'vacant', label: 'Vacant' },
    { value: 'occupied', label: 'Occupied' },
    { value: 'maintenance', label: 'Maintenance' },
];

/**
 * Read query params off Inertia's page.url, NOT window.location — see the same
 * note on the organizations index.
 */
function useQuery(): URLSearchParams {
    const url = usePage().url;

    return useMemo(() => {
        const index = url.indexOf('?');

        return new URLSearchParams(index === -1 ? '' : url.slice(index));
    }, [url]);
}

export default function UnitsIndex({ units, properties }: Props) {
    const query = useQuery();

    const filters = {
        search: query.get('search') ?? '',
        status: query.get('status') ?? '',
        property: query.get('property') ?? '',
    };

    const sort = {
        column: query.get('sort') || null,
        direction: query.get('direction') === 'asc' ? 'asc' : 'desc',
    };

    const isFiltersOpen =
        query.get('filters') !== null && query.get('filters') !== '';

    const [isTableLoading, setIsTableLoading] = useState(false);

    const only = ['units', 'properties'];

    function navigate(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        router.get(unitRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only,
        });
    }

    function setOverlayParam(key: string, value: string | null) {
        navigate(
            {
                search: filters.search,
                status: filters.status,
                property: filters.property,
                sort: sort.column ?? '',
                direction: sort.direction,
                [key]: value ?? '',
            },
            { replace: true },
        );
    }

    function refetchTable(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        setIsTableLoading(true);

        router.get(unitRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only,
            onFinish: () => setIsTableLoading(false),
        });
    }

    function applySorting(next: SortingState) {
        const first = next[0];

        refetchTable(
            {
                search: filters.search,
                status: filters.status,
                property: filters.property,
                sort: first?.id ?? '',
                direction: first ? (first.desc ? 'desc' : 'asc') : sort.direction,
            },
            { replace: true },
        );
    }

    function changePage(nextPage: number) {
        refetchTable(
            {
                search: filters.search,
                status: filters.status,
                property: filters.property,
                sort: sort.column ?? '',
                direction: sort.direction,
                page: nextPage,
            },
            { replace: true },
        );
    }

    // Live search: local input for responsiveness, debounced server round-trip.
    const [searchDraft, setSearchDraft] = useState(filters.search);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        if (searchDraft === filters.search) {
            return;
        }

        const timer = window.setTimeout(() => {
            refetchTable(
                {
                    search: searchDraft,
                    status: filters.status,
                    property: filters.property,
                    sort: sort.column ?? '',
                    direction: sort.direction,
                },
                { replace: true },
            );
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchDraft]);

    const [serverSearch, setServerSearch] = useState(filters.search);

    if (filters.search !== serverSearch) {
        setServerSearch(filters.search);
        setSearchDraft(filters.search);
    }

    const sorting = useMemo<SortingState>(
        () =>
            sort.column
                ? [{ id: sort.column, desc: sort.direction === 'desc' }]
                : [],
        [sort.column, sort.direction],
    );

    const columns = useMemo<ColumnDef<UnitRow, unknown>[]>(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: 'Unit',
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <div className="bg-muted flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-md">
                            {row.original.thumbnail ? (
                                <img
                                    src={row.original.thumbnail}
                                    alt=""
                                    loading="lazy"
                                    className="size-full object-cover"
                                />
                            ) : (
                                <ImageOff className="text-muted-foreground size-4" />
                            )}
                        </div>
                        <div className="min-w-0">
                            <div className="font-medium">
                                {row.original.name}
                            </div>
                            <div className="text-muted-foreground truncate text-xs">
                                {row.original.type ?? '—'}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'property',
                header: 'Property',
                enableSorting: false,
                cell: ({ row }) => (
                    <span className="flex items-center gap-1.5 text-xs">
                        <Building2 className="text-muted-foreground size-3.5 shrink-0" />
                        {row.original.property.name ?? '—'}
                    </span>
                ),
            },
            {
                id: 'rent',
                accessorKey: 'monthly_rent',
                header: 'Rent',
                cell: ({ row }) => (
                    <span className="text-xs tabular-nums">
                        {row.original.monthly_rent ?? '—'}
                    </span>
                ),
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <span className="text-xs capitalize">
                        {row.original.status}
                    </span>
                ),
            },
            {
                id: 'photos',
                accessorKey: 'photos_count',
                header: 'Photos',
                cell: ({ row }) => (
                    <span className="text-xs tabular-nums">
                        {row.original.photos_count}
                    </span>
                ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Units" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Units"
                    description="Every rentable unit across your properties, with the photos attached to each."
                />

                <Card className="gap-0 py-0">
                    <div className="flex flex-wrap items-center gap-2 border-b border-border-strong p-4">
                        <div className="relative min-w-56 flex-1">
                            <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={searchDraft}
                                onChange={(event) =>
                                    setSearchDraft(event.target.value)
                                }
                                placeholder="Search unit, type or property..."
                                aria-label="Search units"
                                className="border-border-strong bg-background pr-3 pl-9"
                            />
                        </div>
                        <ActionIconButton
                            icon={<ListFilter />}
                            label="Filters"
                            color="teal"
                            onClick={() =>
                                isFiltersOpen
                                    ? setOverlayParam('filters', '')
                                    : setOverlayParam('filters', '1')
                            }
                        />
                    </div>

                    <DataTable
                        columns={columns}
                        data={units.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        onSortingChange={applySorting}
                        loading={isTableLoading}
                        onRowClick={(unit) =>
                            router.get(unitRoutes.show(unit.id).url)
                        }
                        empty="No units match your filters."
                    />
                </Card>

                {units.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {units.current_page} of {units.last_page} (
                            {units.total} units)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={units.current_page <= 1}
                                onClick={() =>
                                    changePage(units.current_page - 1)
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={units.current_page >= units.last_page}
                                onClick={() =>
                                    changePage(units.current_page + 1)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <ResponsiveOverlay
                open={isFiltersOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('filters', '');
                    }
                }}
                title="Filters"
                description="Narrow the list of units."
                footer={
                    <OverlayFooter
                        onCancel={() => setOverlayParam('filters', '')}
                        cancelLabel="Close"
                        actions={[
                            {
                                label: 'Clear all',
                                onClick: () => {
                                    setSearchDraft('');
                                    refetchTable(
                                        {
                                            search: '',
                                            status: '',
                                            property: '',
                                            sort: sort.column ?? '',
                                            direction: sort.direction,
                                        },
                                        { replace: true },
                                    );
                                    setOverlayParam('filters', '');
                                },
                            },
                        ]}
                    />
                }
            >
                <div className="grid gap-4 py-2">
                    <fieldset className="space-y-3">
                        <legend className="text-sm font-medium">
                            Status
                        </legend>
                        <RadioGroup
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                refetchTable({
                                    search: filters.search,
                                    status: value === 'all' ? '' : value,
                                    property: filters.property,
                                    sort: sort.column ?? '',
                                    direction: sort.direction,
                                    filters: '1',
                                })
                            }
                            className="space-y-3"
                        >
                            {[
                                { value: 'all', label: 'Any status' },
                                ...STATUSES,
                            ].map((option) => (
                                <div
                                    key={option.value}
                                    className="flex items-center gap-2"
                                >
                                    <RadioGroupItem
                                        id={`unit-status-${option.value}`}
                                        value={option.value}
                                    />
                                    <Label
                                        htmlFor={`unit-status-${option.value}`}
                                        className="cursor-pointer"
                                    >
                                        {option.label}
                                    </Label>
                                </div>
                            ))}
                        </RadioGroup>
                    </fieldset>

                    <div className="grid gap-2">
                        <Label htmlFor="unit-property">Property</Label>
                        <Input
                            id="unit-property"
                            type="text"
                            readOnly
                            placeholder={
                                filters.property
                                    ? (properties.find(
                                          (p) =>
                                              String(p.id) === filters.property,
                                      )?.name ?? '')
                                    : 'Any property'
                            }
                            onClick={() =>
                                refetchTable({
                                    search: filters.search,
                                    status: filters.status,
                                    property: '',
                                    sort: sort.column ?? '',
                                    direction: sort.direction,
                                    filters: '1',
                                })
                            }
                        />
                        <p className="text-muted-foreground text-xs">
                            Pick a property from the filter list below.
                        </p>
                    </div>

                    {properties.length > 0 && (
                        <fieldset className="space-y-3">
                            <legend className="text-sm font-medium">
                                Choose a property
                            </legend>
                            <div className="flex flex-wrap gap-2">
                                {properties.map((property) => (
                                    <button
                                        key={property.id}
                                        type="button"
                                        aria-pressed={
                                            filters.property ===
                                            String(property.id)
                                        }
                                        onClick={() =>
                                            refetchTable({
                                                search: filters.search,
                                                status: filters.status,
                                                property:
                                                    filters.property ===
                                                    String(property.id)
                                                        ? ''
                                                        : String(
                                                              property.id,
                                                          ),
                                                sort: sort.column ?? '',
                                                direction: sort.direction,
                                                filters: '1',
                                            })
                                        }
                                        className={`rounded-md border px-3 py-1.5 text-xs ${
                                            filters.property ===
                                            String(property.id)
                                                ? 'border-primary bg-primary/5 font-medium'
                                                : 'border-border-strong'
                                        }`}
                                    >
                                        {property.name}
                                    </button>
                                ))}
                            </div>
                        </fieldset>
                    )}
                </div>
            </ResponsiveOverlay>
        </>
    );
}

UnitsIndex.layout = {
    breadcrumbs: [{ title: 'Units' }],
};