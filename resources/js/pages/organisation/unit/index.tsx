import { Head, router, Form } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    Building2,
    Download,
    ImageOff,
    ListFilter,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import ConfirmDeleteOverlay from '@/components/confirm-delete-overlay';
import { downloadCsv } from '@/components/csv-actions';
import { CsvExportSheet } from '@/components/csv-export-sheet';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import { PhotoPicker } from '@/components/photo-picker';
import type { PhotoLibraryUnit } from '@/components/photo-picker';
import { SearchableSelect } from '@/components/searchable-select';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatMoney } from '@/lib/format-money';
import { duplicateUnitName } from '@/lib/unit-names';
import { FrequencySelect } from '@/components/frequency-select';
import { cn } from '@/lib/utils';
import { store as storeUnit } from '@/routes/tenant/properties/units';
import * as unitRoutes from '@/routes/tenant/units';

type UnitRow = {
    id: number;
    name: string;
    slug: string;
    type: string | null;
    status: string;
    rent_amount: string | null;
    rent_frequency: string | null;
    photos_count: number;
    thumbnail: string | null;
    photos: { id: number; url: string; name: string; cover: boolean }[];
    property: { id: number | null; name: string | null; slug: string | null };
};

type Props = {
    units: {
        data: UnitRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    properties: {
        id: number;
        name: string;
        slug: string;
        units_count: number;
        unit_names: string[];
    }[];
    plan: { units_limit: number | null };
    /** Admin-curated catalogue backing the type dropdown. */
    unitTypes: string[];
    /** Other units' photos, offered for reuse. */
    photoLibrary: PhotoLibraryUnit[];
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

export default function UnitsIndex({
    units,
    properties,
    plan,
    unitTypes,
    photoLibrary,
}: Props) {
    const query = useQuery();

    const { context } = usePage().props;
    const canCreate = (context?.permissions ?? []).includes('unit.create');
    const canEdit = (context?.permissions ?? []).includes('unit.edit');
    const canDelete = (context?.permissions ?? []).includes('unit.delete');
    // Export rides on the same gate as the page itself: anyone trusted to
    // browse the units may download the same list. No unit.export key exists.
    const canExport = (context?.permissions ?? []).includes('unit.manage');

    const [editTarget, setEditTarget] = useState<UnitRow | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<UnitRow | null>(null);
    const [editPropertyId, setEditPropertyId] = useState<string | null>(null);
    const [createNameError, setCreateNameError] = useState<string | null>(null);
    const [editNameError, setEditNameError] = useState<string | null>(null);
    const [createFrequency, setCreateFrequency] = useState('monthly');
    const [editFrequency, setEditFrequency] = useState('monthly');
    const [coverPhotoId, setCoverPhotoId] = useState<number | null>(null);

    // The property picker is local state, so reseed it per row. The form
    // below remounts by key, but this state lives outside it.
    useEffect(() => {
        setEditPropertyId(
            editTarget ? String(editTarget.property.id ?? '') : null,
        );
        setEditNameError(null);
        setEditFrequency(editTarget?.rent_frequency ?? 'monthly');
        setCoverPhotoId(
            editTarget?.photos.find((photo) => photo.cover)?.id ?? null,
        );
    }, [editTarget?.id]);

    // The delete overlay is local state, so it closes itself once the
    // fresh props come back without the row. A failed confirm (wrong
    // password, freshly occupied) keeps the row — and the overlay — put.
    useEffect(() => {
        if (
            deleteTarget !== null &&
            !units.data.some((unit) => unit.id === deleteTarget.id)
        ) {
            setDeleteTarget(null);
        }
    }, [units.data, deleteTarget]);

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
                direction: first
                    ? first.desc
                        ? 'desc'
                        : 'asc'
                    : sort.direction,
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

    const isCreateOpen = query.get('create') === '1';
    const isExportOpen = query.get('export') === '1';

    /** Spoken description of the active filters, for the export sheet. */
    const activeFilterSummary =
        [
            filters.search ? 'the search' : '',
            filters.status ? 'the status filter' : '',
            filters.property ? 'the property filter' : '',
        ]
            .filter(Boolean)
            .join(' and ') || null;

    /**
     * The filters the export has to carry. Reusing them is the point: "Export
     * all" must mean "everything the table is showing", not "everything".
     */
    function exportParams(scope: 'all' | 'page') {
        return {
            search: filters.search,
            status: filters.status,
            property: filters.property,
            sort: sort.column ?? '',
            direction: sort.direction,
            scope,
            ...(scope === 'page' ? { page: units.current_page } : {}),
        };
    }

    function runExport(scope: 'all' | 'page') {
        downloadCsv(unitRoutes.exportMethod().url, exportParams(scope));
        setOverlayParam('export', null);
    }

    // Default to the filtered property so "Add unit" from a filtered list
    // lands in the right building; fall back to the first property.
    const [createPropertyId, setCreatePropertyId] = useState(
        filters.property ||
            (properties.length > 0 ? String(properties[0].id) : ''),
    );

    const createProperty = properties.find(
        (property) => String(property.id) === createPropertyId,
    );
    const atUnitLimit =
        plan.units_limit !== null &&
        (createProperty?.units_count ?? 0) >= plan.units_limit;

    function openCreate() {
        setOverlayParam('create', '1');
    }

    function closeCreate() {
        refetchTable({
            search: filters.search,
            status: filters.status,
            property: filters.property,
            sort: sort.column ?? '',
            direction: sort.direction,
        });
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
                        <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted">
                            {row.original.thumbnail ? (
                                <img
                                    src={row.original.thumbnail}
                                    alt=""
                                    loading="lazy"
                                    className="size-full object-cover"
                                />
                            ) : (
                                <ImageOff className="size-4 text-muted-foreground" />
                            )}
                        </div>
                        <div className="min-w-0">
                            <div className="font-medium">
                                {row.original.name}
                            </div>
                            <div className="truncate text-xs text-muted-foreground">
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
                        <Building2 className="size-3.5 shrink-0 text-muted-foreground" />
                        {row.original.property.name ?? '—'}
                    </span>
                ),
            },
            {
                id: 'rent',
                accessorKey: 'rent_amount',
                header: 'Rent',
                cell: ({ row }) => (
                    <span className="text-xs tabular-nums">
                        {formatMoney(row.original.rent_amount)}
                        {row.original.rent_frequency === 'daily'
                            ? '/day'
                            : row.original.rent_frequency === 'weekly'
                              ? '/wk'
                              : row.original.rent_frequency === 'yearly'
                                ? '/yr'
                                : row.original.rent_amount !== null
                                  ? '/mo'
                                  : ''}
                    </span>
                ),
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <Badge
                        variant={
                            row.original.status === 'occupied'
                                ? 'success'
                                : row.original.status === 'maintenance'
                                  ? 'warning'
                                  : 'neutral'
                        }
                        className="text-xs capitalize"
                    >
                        {row.original.status}
                    </Badge>
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
            {
                id: 'actions',
                header: 'Actions',
                enableSorting: false,
                meta: { align: 'right' },
                cell: ({ row }) => {
                    const unit = row.original;
                    const occupied = unit.status === 'occupied';

                    return (
                        <div
                            className="flex justify-end gap-1"
                            onClick={(event) => event.stopPropagation()}
                        >
                            {canEdit && (
                                <ActionIconButton
                                    icon={<Pencil />}
                                    label={`Edit ${unit.name}`}
                                    color="edit"
                                    onClick={() => setEditTarget(unit)}
                                />
                            )}
                            {canDelete && (
                                <ActionIconButton
                                    icon={<Trash2 />}
                                    label={`Delete ${unit.name}`}
                                    color="danger"
                                    disabledReason={
                                        occupied
                                            ? 'Occupied units cannot be deleted. End the lease first.'
                                            : null
                                    }
                                    onClick={() => setDeleteTarget(unit)}
                                />
                            )}
                        </div>
                    );
                },
            },
        ],
        [canEdit, canDelete],
    );

    return (
        <>
            <Head title="Units" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        level="h1"
                        title="Units"
                        description="Every rentable unit across your properties, with the photos attached to each."
                    />
                    <div className="flex flex-wrap items-start gap-3">
                        {canCreate && properties.length > 0 && (
                            <LabelledIconButton
                                icon={<Plus />}
                                label="Add unit"
                                tooltip="Add a unit to one of your properties"
                                color="primary"
                                onClick={openCreate}
                                className="w-auto max-w-20 sm:max-w-24"
                            />
                        )}
                        {canExport && (
                            <LabelledIconButton
                                icon={<Download />}
                                label="Export"
                                tooltip="Download the filtered units as CSV"
                                color="teal"
                                onClick={() => setOverlayParam('export', '1')}
                                className="w-auto max-w-20 sm:max-w-24"
                            />
                        )}
                    </div>
                </div>

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

                <Pagination
                    currentPage={units.current_page}
                    lastPage={units.last_page}
                    total={units.total}
                    label="units"
                    onChange={changePage}
                />
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
                        <legend className="text-sm font-medium">Status</legend>
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
                        <Button
                            id="unit-property"
                            type="button"
                            variant="outline"
                            className="justify-start font-normal"
                            aria-label={
                                filters.property
                                    ? `Property filter: ${properties.find((p) => String(p.id) === filters.property)?.name ?? ''}. Activate to clear.`
                                    : 'Property filter: any property.'
                            }
                            onClick={() =>
                                filters.property &&
                                refetchTable({
                                    search: filters.search,
                                    status: filters.status,
                                    property: '',
                                    sort: sort.column ?? '',
                                    direction: sort.direction,
                                    filters: '1',
                                })
                            }
                        >
                            {filters.property
                                ? (properties.find(
                                      (p) => String(p.id) === filters.property,
                                  )?.name ?? '')
                                : 'Any property'}
                        </Button>
                        <p className="text-xs text-muted-foreground">
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
                                                        : String(property.id),
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

            <ResponsiveOverlay
                open={isCreateOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('create', null);
                    }
                }}
                title="Add unit"
                description="Rental units live under a property — pick the building first."
                footer={
                    <OverlayFooter
                        onCancel={() => setOverlayParam('create', null)}
                        cancelLabel="Cancel"
                        actions={[
                            {
                                label: 'Add unit',
                                form: 'unit-create-form',
                                type: 'submit',
                                disabled:
                                    atUnitLimit || createNameError !== null,
                            },
                        ]}
                    />
                }
            >
                {atUnitLimit && (
                    <p className="text-sm text-destructive">
                        {createProperty?.name} already has{' '}
                        {createProperty?.units_count} units, the plan maximum.
                        Upgrade the plan to add more here, or pick another
                        property.
                    </p>
                )}
                {createProperty && (
                    <Form
                        id="unit-create-form"
                        method="post"
                        action={storeUnit(createProperty.slug)}
                        options={{ preserveScroll: true }}
                        onSuccess={closeCreate}
                    >
                        {({ errors }) => (
                            <div className="grid gap-4 py-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="create-unit-property">
                                        Property
                                    </Label>
                                    <Select
                                        value={String(createProperty.id)}
                                        onValueChange={setCreatePropertyId}
                                    >
                                        <SelectTrigger id="create-unit-property">
                                            <SelectValue placeholder="Choose a property" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {properties.map((property) => (
                                                <SelectItem
                                                    key={property.id}
                                                    value={String(property.id)}
                                                >
                                                    {property.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="create-unit-name">
                                        Unit name
                                    </Label>
                                    <Input
                                        id="create-unit-name"
                                        name="name"
                                        type="text"
                                        required
                                        placeholder="A1"
                                        onChange={() =>
                                            setCreateNameError(null)
                                        }
                                        onBlur={(event) => {
                                            const clash =
                                                createProperty == null
                                                    ? null
                                                    : duplicateUnitName(
                                                          event.target.value,
                                                          createProperty.unit_names,
                                                      );
                                            setCreateNameError(
                                                clash === null
                                                    ? null
                                                    : `A unit called “${clash}” already exists in ${createProperty?.name}.`,
                                            );
                                        }}
                                    />
                                    <InputError
                                        message={errors.name ?? createNameError}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="create-unit-type">
                                        Type
                                    </Label>
                                    <SearchableSelect
                                        id="create-unit-type"
                                        name="type"
                                        options={unitTypes}
                                        placeholder="Select a type…"
                                        searchPlaceholder="Search types…"
                                    />
                                    <InputError message={errors.type} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="create-unit-frequency">
                                        Billing frequency
                                    </Label>
                                    <FrequencySelect
                                        id="create-unit-frequency"
                                        name="rent_frequency"
                                        value={createFrequency}
                                        onValueChange={setCreateFrequency}
                                    />
                                    <InputError
                                        message={errors.rent_frequency}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="create-unit-rent">
                                        Rent amount
                                    </Label>
                                    <Input
                                        id="create-unit-rent"
                                        name="rent_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="25000"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Amount the tenant pays each{' '}
                                        {createFrequency === 'daily'
                                            ? 'day'
                                            : createFrequency === 'weekly'
                                              ? 'week'
                                              : createFrequency === 'yearly'
                                                ? 'year'
                                                : 'month'}
                                        .
                                    </p>
                                    <InputError message={errors.rent_amount} />
                                </div>
                                <div className="grid gap-2">
                                    <span className="text-sm leading-none font-medium">
                                        Photos{' '}
                                        <span className="font-normal text-muted-foreground">
                                            (optional)
                                        </span>
                                    </span>
                                    <PhotoPicker
                                        id="create-unit-photos"
                                        library={photoLibrary}
                                        error={
                                            Object.entries(errors ?? {}).find(
                                                ([key]) =>
                                                    key === 'photos' ||
                                                    key.startsWith('photos.') ||
                                                    key === 'reuse_photo_ids' ||
                                                    key.startsWith(
                                                        'reuse_photo_ids.',
                                                    ),
                                            )?.[1] as string | undefined
                                        }
                                    />
                                </div>
                                <InputError message={errors.plan} />
                            </div>
                        )}
                    </Form>
                )}
            </ResponsiveOverlay>

            {canEdit && editTarget !== null && (
                <ResponsiveOverlay
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditTarget(null);
                        }
                    }}
                    title={`Edit ${editTarget.name}`}
                    description="Update the property, name, type, rent or photos for this unit."
                    footer={
                        <OverlayFooter
                            onCancel={() => setEditTarget(null)}
                            actions={[
                                {
                                    label: 'Save changes',
                                    form: 'unit-edit-form',
                                    type: 'submit',
                                    disabled: editNameError !== null,
                                },
                            ]}
                        />
                    }
                >
                    <Form
                        key={editTarget.id}
                        id="unit-edit-form"
                        method="put"
                        action={unitRoutes.update(editTarget.id).url}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setEditTarget(null)}
                        className="grid gap-4 py-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-unit-property">
                                        Property
                                    </Label>
                                    <Select
                                        value={editPropertyId ?? ''}
                                        onValueChange={setEditPropertyId}
                                        disabled={
                                            editTarget.status === 'occupied'
                                        }
                                    >
                                        <SelectTrigger id="edit-unit-property">
                                            <SelectValue placeholder="Choose a property" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {properties.map((property) => (
                                                <SelectItem
                                                    key={property.id}
                                                    value={String(property.id)}
                                                >
                                                    {property.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <input
                                        type="hidden"
                                        name="property_id"
                                        value={editPropertyId ?? ''}
                                    />
                                    {editTarget.status === 'occupied' ? (
                                        <p className="text-xs text-muted-foreground">
                                            Occupied units stay in their
                                            property until the lease ends.
                                        </p>
                                    ) : (
                                        <InputError
                                            message={errors.property_id}
                                        />
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-unit-name">
                                        Unit name
                                    </Label>
                                    <Input
                                        id="edit-unit-name"
                                        name="name"
                                        type="text"
                                        required
                                        defaultValue={editTarget.name}
                                        onChange={() => setEditNameError(null)}
                                        onBlur={(event) => {
                                            const names =
                                                properties.find(
                                                    (property) =>
                                                        String(property.id) ===
                                                        editPropertyId,
                                                )?.unit_names ?? [];
                                            const clash = duplicateUnitName(
                                                event.target.value,
                                                names,
                                                editTarget.name,
                                            );
                                            setEditNameError(
                                                clash === null
                                                    ? null
                                                    : `A unit called “${clash}” already exists here.`,
                                            );
                                        }}
                                    />
                                    <InputError
                                        message={errors.name ?? editNameError}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-unit-type">Type</Label>
                                    <SearchableSelect
                                        id="edit-unit-type"
                                        name="type"
                                        options={unitTypes}
                                        defaultValue={editTarget.type ?? ''}
                                        placeholder="Select a type…"
                                        searchPlaceholder="Search types…"
                                    />
                                    <InputError message={errors.type} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-unit-frequency">
                                        Billing frequency
                                    </Label>
                                    <FrequencySelect
                                        id="edit-unit-frequency"
                                        name="rent_frequency"
                                        value={editFrequency}
                                        onValueChange={setEditFrequency}
                                    />
                                    <InputError
                                        message={errors.rent_frequency}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-unit-rent">
                                        Rent amount
                                    </Label>
                                    <Input
                                        id="edit-unit-rent"
                                        name="rent_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        defaultValue={
                                            editTarget.rent_amount ?? ''
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Amount the tenant pays each{' '}
                                        {editFrequency === 'daily'
                                            ? 'day'
                                            : editFrequency === 'weekly'
                                              ? 'week'
                                              : editFrequency === 'yearly'
                                                ? 'year'
                                                : 'month'}
                                        .
                                    </p>
                                    <InputError message={errors.rent_amount} />
                                </div>
                                <div className="grid gap-2">
                                    <span className="text-sm leading-none font-medium">
                                        Add photos{' '}
                                        <span className="font-normal text-muted-foreground">
                                            (optional)
                                        </span>
                                    </span>
                                    {editTarget.photos.length > 0 && (
                                        <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                            {editTarget.photos.map((photo) => {
                                                const isCover =
                                                    coverPhotoId === photo.id;

                                                return (
                                                    <div
                                                        key={photo.id}
                                                        className={cn(
                                                            'group relative overflow-hidden rounded-lg border',
                                                            isCover
                                                                ? 'border-brand-primary ring-2 ring-brand-primary/40'
                                                                : 'border-border-strong',
                                                        )}
                                                    >
                                                        <img
                                                            src={photo.url}
                                                            alt={photo.name}
                                                            loading="lazy"
                                                            className="aspect-square w-full object-cover"
                                                        />
                                                        {isCover ? (
                                                            <span className="absolute bottom-1 left-1 rounded bg-brand-primary px-1 text-[10px] font-medium text-white">
                                                                Cover
                                                            </span>
                                                        ) : (
                                                            <button
                                                                type="button"
                                                                aria-label={`Set ${photo.name} as cover`}
                                                                title="Set as cover"
                                                                onClick={() =>
                                                                    setCoverPhotoId(
                                                                        photo.id,
                                                                    )
                                                                }
                                                                className="absolute inset-x-1 bottom-1 rounded bg-background/90 px-1 py-0.5 text-[10px] font-medium text-muted-foreground opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100 hover:text-foreground"
                                                            >
                                                                Set as cover
                                                            </button>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                    <input
                                        type="hidden"
                                        name="cover_photo_id"
                                        value={coverPhotoId ?? ''}
                                    />
                                    <PhotoPicker
                                        id="edit-unit-photos"
                                        library={photoLibrary}
                                        excludeUnitId={editTarget.id}
                                        alreadyAttached={
                                            editTarget.photos_count
                                        }
                                        error={
                                            Object.entries(errors ?? {}).find(
                                                ([key]) =>
                                                    key === 'photos' ||
                                                    key.startsWith('photos.') ||
                                                    key === 'reuse_photo_ids' ||
                                                    key.startsWith(
                                                        'reuse_photo_ids.',
                                                    ),
                                            )?.[1] as string | undefined
                                        }
                                    />
                                </div>
                                {processing && (
                                    <p className="text-sm text-muted-foreground">
                                        Saving…
                                    </p>
                                )}
                            </>
                        )}
                    </Form>
                </ResponsiveOverlay>
            )}

            {canDelete && deleteTarget !== null && (
                <ConfirmDeleteOverlay
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setDeleteTarget(null);
                        }
                    }}
                    action={unitRoutes.destroy(deleteTarget.id).url}
                    title={`Delete ${deleteTarget.name}?`}
                    description="This removes the unit and its photos. Occupied units cannot be deleted — end the lease first."
                />
            )}

            <CsvExportSheet
                open={isExportOpen}
                onClose={() => setOverlayParam('export', null)}
                canExport={canExport}
                total={units.total}
                pageCount={units.data.length}
                currentPage={units.current_page}
                activeFilters={activeFilterSummary}
                onExport={runExport}
            />
        </>
    );
}

UnitsIndex.layout = {
    breadcrumbs: [{ title: 'Units' }],
};
