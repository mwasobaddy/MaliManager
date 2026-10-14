import { Head, router } from '@inertiajs/react';
import { Form, usePage } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    Download,
    ListFilter,
    Pencil,
    Plus,
    Search,
    Tag,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { downloadCsv } from '@/components/csv-actions';
import { CsvExportSheet } from '@/components/csv-export-sheet';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import * as typeRoutes from '@/routes/tenant/property-types';

type PropertyTypeRow = {
    id: number;
    name: string;
    slug: string;
    category: 'residential' | 'commercial';
    category_label: string;
    sort_order: number;
    status: string;
    description: string | null;
    properties_count: number;
};

type Props = {
    propertyTypes: {
        data: PropertyTypeRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    counts: { residential: number; commercial: number };
    canCreate: boolean;
    canEdit: boolean;
    canDelete: boolean;
};

/** Debounce so a fast typist issues one request, not one per keystroke. */
const SEARCH_DEBOUNCE_MS = 350;

const CATEGORIES = [
    { value: 'residential', label: 'Residential' },
    { value: 'commercial', label: 'Commercial' },
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

export default function PropertyTypesIndex({
    propertyTypes,
    counts,
    canCreate,
    canEdit,
    canDelete,
}: Props) {
    const page = usePage();
    const query = useQuery();
    const errors = (page.props.errors ?? {}) as Record<string, string>;

    const filters = {
        search: query.get('search') ?? '',
        category: query.get('category') ?? '',
    };

    const sort = {
        column: query.get('sort') || null,
        direction: query.get('direction') === 'asc' ? 'asc' : 'desc',
    };

    type RowMode = 'view' | 'edit' | 'delete';
    const rowMode = (query.get('mode') as RowMode | null) ?? 'view';
    const isViewOpen = query.get('id') !== null;
    const isCreateOpen = query.get('create') === '1';
    const isFiltersOpen =
        query.get('filters') !== null && query.get('filters') !== '';
    const isImportOpen = query.get('import') === '1';
    const isExportOpen = query.get('export') === '1';

    const [isTableLoading, setIsTableLoading] = useState(false);

    /** Spoken description of the active filters, for the export sheet. */
    const activeFilterSummary =
        [
            filters.search ? 'the search' : '',
            filters.category ? 'the category filter' : '',
        ]
            .filter(Boolean)
            .join(' and ') || null;

    const only = [
        'propertyTypes',
        'counts',
        'canCreate',
        'canEdit',
        'canDelete',
    ];

    /** Changes only the URL, never the result set. */
    function navigate(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        router.get(typeRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only,
        });
    }

    function refetchTable(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        setIsTableLoading(true);

        router.get(typeRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only,
            onFinish: () => setIsTableLoading(false),
        });
    }

    function setOverlayParam(
        key: string,
        value: string | null,
        options?: {
            replace?: boolean;
            keep?: Record<string, string>;
        },
    ) {
        navigate(
            {
                search: filters.search,
                category: filters.category,
                sort: sort.column ?? '',
                direction: sort.direction,
                ...(options?.keep ?? {}),
                [key]: value ?? '',
            },
            { replace: options?.replace },
        );
    }

    function closeRowSheet() {
        navigate(
            {
                search: filters.search,
                category: filters.category,
                sort: sort.column ?? '',
                direction: sort.direction,
            },
            { replace: true },
        );
    }

    function openRow(type: PropertyTypeRow, mode: RowMode) {
        setOverlayParam('mode', mode, { keep: { id: String(type.id) } });
    }

    function applySorting(next: SortingState) {
        const first = next[0];

        refetchTable(
            {
                search: filters.search,
                category: filters.category,
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
                category: filters.category,
                sort: sort.column ?? '',
                direction: sort.direction,
                page: nextPage,
            },
            { replace: true },
        );
    }

    function exportParams(scope: 'all' | 'page') {
        return {
            search: filters.search,
            category: filters.category,
            scope,
            ...(scope === 'page' ? { page: propertyTypes.current_page } : {}),
        };
    }

    function runExport(scope: 'all' | 'page') {
        downloadCsv(typeRoutes.exportMethod().url, exportParams(scope));
        setOverlayParam('export', null, { replace: true });
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
                    category: filters.category,
                    sort: sort.column ?? '',
                    direction: sort.direction,
                },
                { replace: true },
            );
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchDraft]);

    // Keep the box in step with Back/Forward and with the filters overlay
    // clearing the search.
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

    const columns = useMemo<ColumnDef<PropertyTypeRow, unknown>[]>(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: 'Type',
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <Tag className="size-4 shrink-0 text-muted-foreground" />
                        <span className="font-medium">{row.original.name}</span>
                    </div>
                ),
            },
            {
                id: 'category',
                accessorKey: 'category',
                header: 'Category',
                cell: ({ row }) => (
                    <span
                        className={
                            row.original.category === 'commercial'
                                ? 'text-xs font-medium text-blue-600 dark:text-blue-400'
                                : 'text-xs font-medium text-emerald-600 dark:text-emerald-400'
                        }
                    >
                        {row.original.category_label}
                    </span>
                ),
            },
            {
                id: 'properties_count',
                accessorKey: 'properties_count',
                header: 'In use',
                cell: ({ row }) => (
                    <span className="text-xs tabular-nums">
                        {row.original.properties_count}
                    </span>
                ),
            },
            {
                id: 'actions',
                header: 'Actions',
                enableSorting: false,
                cell: ({ row }) => {
                    const type = row.original;

                    return (
                        <div
                            className="flex justify-end gap-1.5"
                            onClick={(event) => event.stopPropagation()}
                        >
                            <ActionIconButton
                                icon={<Pencil />}
                                label={`Edit ${type.name}`}
                                color="edit"
                                disabled={!canEdit}
                                onClick={() => openRow(type, 'edit')}
                            />
                            <ActionIconButton
                                icon={<Trash2 />}
                                label={`Delete ${type.name}`}
                                color="danger"
                                disabled={!canDelete}
                                onClick={() => openRow(type, 'delete')}
                            />
                        </div>
                    );
                },
            },
        ],
        // `openRow` is redefined every render, so including it would rebuild the
        // columns on every keystroke in the search box.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [canEdit, canDelete],
    );

    const viewedType = isViewOpen
        ? propertyTypes.data.find((type) => type.id === Number(query.get('id')))
        : undefined;

    return (
        <>
            <Head title="Property types" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-3">
                    <Heading
                        title="Property types"
                        description="The labels you can apply to a property, grouped as residential or commercial."
                    />
                    {canCreate && (
                        <LabelledIconButton
                            icon={<Plus />}
                            label="New type"
                            tooltip="Create property type"
                            color="primary"
                            onClick={() => setOverlayParam('create', '1')}
                            className="max-w-20 sm:max-w-24"
                        />
                    )}
                </div>

                <div className="flex flex-wrap gap-2 text-sm">
                    <Pill label="Residential" value={counts.residential} />
                    <Pill label="Commercial" value={counts.commercial} />
                    <Pill label="Total" value={propertyTypes.total} />
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
                                placeholder="Search property types..."
                                aria-label="Search property types"
                                className="border-border-strong bg-background pr-3 pl-9"
                            />
                        </div>
                        <ActionIconButton
                            icon={<ListFilter />}
                            label="Filters"
                            color="teal"
                            onClick={() =>
                                isFiltersOpen
                                    ? setOverlayParam('filters', null, {
                                          replace: true,
                                      })
                                    : setOverlayParam('filters', '1')
                            }
                        />
                        <CsvExportTrigger
                            open={isExportOpen}
                            setOverlayParam={setOverlayParam}
                        />
                    </div>

                    <DataTable
                        columns={columns}
                        data={propertyTypes.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        onSortingChange={applySorting}
                        loading={isTableLoading}
                        onRowClick={(type) => openRow(type, 'view')}
                        empty="No property types match your filters."
                    />
                </Card>

                {propertyTypes.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {propertyTypes.current_page} of{' '}
                            {propertyTypes.last_page} ({propertyTypes.total}{' '}
                            types)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={propertyTypes.current_page <= 1}
                                onClick={() =>
                                    changePage(propertyTypes.current_page - 1)
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    propertyTypes.current_page >=
                                    propertyTypes.last_page
                                }
                                onClick={() =>
                                    changePage(propertyTypes.current_page + 1)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <ResponsiveOverlay
                open={isCreateOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('create', null, { replace: true });
                    }
                }}
                title="Create property type"
                description="A label you can apply to a property."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('create', null, { replace: true })
                        }
                        actions={[
                            {
                                label: 'Create type',
                                form: 'property-type-create-form',
                                type: 'submit',
                            },
                        ]}
                    />
                }
            >
                <Form
                    id="property-type-create-form"
                    method="post"
                    action={typeRoutes.store()}
                    onSuccess={() =>
                        setOverlayParam('create', null, { replace: true })
                    }
                    className="grid gap-3 py-2"
                >
                    <PropertyTypeFields idPrefix="create" errors={errors} />
                </Form>
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isViewOpen && viewedType !== undefined}
                onOpenChange={(open) => {
                    if (!open) {
                        closeRowSheet();
                    }
                }}
                title={viewedType?.name ?? 'Property type'}
                description={
                    !viewedType
                        ? 'This type is not on the current page of results.'
                        : rowMode === 'edit'
                          ? 'Rename it, recategorise it, or reorder it.'
                          : rowMode === 'delete'
                            ? 'Properties using it are kept and simply lose their type.'
                            : 'Details for this type.'
                }
                footer={
                    rowMode === 'edit' && viewedType ? (
                        <OverlayFooter
                            onCancel={closeRowSheet}
                            actions={[
                                {
                                    label: 'Save changes',
                                    form: 'property-type-edit-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    ) : rowMode === 'delete' && viewedType ? (
                        <OverlayFooter
                            onCancel={closeRowSheet}
                            actions={[
                                {
                                    label: 'Delete type',
                                    tone: 'danger',
                                    icon: <Trash2 className="size-4" />,
                                    form: 'property-type-delete-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    ) : viewedType ? (
                        <OverlayFooter
                            actions={[
                                {
                                    label: 'Edit',
                                    tone: 'secondary',
                                    icon: <Pencil className="size-4" />,
                                    onClick: () =>
                                        openRow(viewedType, 'edit'),
                                    ...(canEdit ? {} : { disabled: true }),
                                },
                                {
                                    label: 'Delete',
                                    tone: 'danger-ghost',
                                    icon: <Trash2 className="size-4" />,
                                    onClick: () =>
                                        openRow(viewedType, 'delete'),
                                    ...(canDelete ? {} : { disabled: true }),
                                },
                            ]}
                        />
                    ) : null
                }
            >
                {viewedType && rowMode === 'view' && (
                    <PropertyTypeDetail type={viewedType} />
                )}

                {viewedType && rowMode === 'edit' && (
                    <Form
                        key={viewedType.id}
                        id="property-type-edit-form"
                        method="patch"
                        action={typeRoutes.update({ propertyType: viewedType.id })}
                        onSuccess={() => closeRowSheet()}
                        className="grid gap-3 py-2"
                    >
                        <PropertyTypeFields
                            idPrefix="edit"
                            type={viewedType}
                            errors={errors}
                        />
                    </Form>
                )}

                {viewedType && rowMode === 'delete' && (
                    <Form
                        id="property-type-delete-form"
                        method="delete"
                        action={typeRoutes.destroy({
                            propertyType: viewedType.id,
                        })}
                        onSuccess={() =>
                            navigate(
                                {
                                    search: filters.search,
                                    category: filters.category,
                                    sort: sort.column ?? '',
                                    direction: sort.direction,
                                },
                                { replace: true },
                            )
                        }
                        className="grid gap-3"
                    >
                        <p className="text-sm">
                            Delete <strong>{viewedType.name}</strong>?
                            {viewedType.properties_count > 0 ? (
                                <>
                                    {' '}
                                    {viewedType.properties_count} propert
                                    {viewedType.properties_count === 1
                                        ? 'y uses'
                                        : 'ies use'}{' '}
                                    it — they are kept and simply lose their
                                    type.
                                </>
                            ) : (
                                ' No properties use it.'
                            )}{' '}
                            This cannot be undone.
                        </p>
                    </Form>
                )}
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isFiltersOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('filters', null, { replace: true });
                    }
                }}
                title="Filters"
                description="Narrow the list of property types."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('filters', null, { replace: true })
                        }
                        cancelLabel="Close"
                        actions={[
                            {
                                label: 'Clear all',
                                onClick: () => {
                                    setSearchDraft('');
                                    refetchTable(
                                        {
                                            search: '',
                                            category: '',
                                            sort: sort.column ?? '',
                                            direction: sort.direction,
                                        },
                                        { replace: true },
                                    );
                                    setOverlayParam('filters', null, {
                                        replace: true,
                                    });
                                },
                            },
                        ]}
                    />
                }
            >
                <fieldset className="space-y-3 py-2">
                    <legend className="text-sm font-medium">Category</legend>
                    <RadioGroup
                        value={filters.category || 'all'}
                        onValueChange={(value) =>
                            refetchTable(
                                {
                                    search: filters.search,
                                    category: value === 'all' ? '' : value,
                                    sort: sort.column ?? '',
                                    direction: sort.direction,
                                    filters: '1',
                                },
                                { replace: true },
                            )
                        }
                        className="space-y-3"
                    >
                        {[
                            { value: 'all', label: 'Residential and commercial' },
                            ...CATEGORIES,
                        ].map((option) => (
                            <div
                                key={option.value}
                                className="flex items-center gap-2"
                            >
                                <RadioGroupItem
                                    id={`type-category-${option.value}`}
                                    value={option.value}
                                />
                                <Label
                                    htmlFor={`type-category-${option.value}`}
                                    className="cursor-pointer"
                                >
                                    {option.label}
                                </Label>
                            </div>
                        ))}
                    </RadioGroup>
                </fieldset>
            </ResponsiveOverlay>

            <CsvExportSheet
                open={isExportOpen}
                onClose={() => setOverlayParam('export', null, { replace: true })}
                canExport
                total={propertyTypes.total}
                pageCount={propertyTypes.data.length}
                currentPage={propertyTypes.current_page}
                activeFilters={activeFilterSummary}
                onExport={runExport}
            />
        </>
    );
}

function Pill({ label, value }: { label: string; value: number }) {
    return (
        <span className="bg-background flex items-center gap-2 rounded-md border border-border-strong px-3 py-1.5">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium tabular-nums">{value}</span>
        </span>
    );
}

/**
 * The export button. Always rendered — the export endpoint is gated only by
 * tenancy, not by a sub-permission, so there is nothing to hide it behind.
 */
function CsvExportTrigger({
    open,
    setOverlayParam,
}: {
    open: boolean;
    setOverlayParam: (
        key: string,
        value: string | null,
        options?: { replace?: boolean },
    ) => void;
}) {
    return (
        <ActionIconButton
            icon={<Download />}
            label="Export"
            color="teal"
            onClick={() =>
                setOverlayParam('export', open ? null : '1', { replace: true })
            }
        />
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="text-xs text-destructive">{message}</p>;
}

/** Read-only summary, for the row sheet's default mode. */
function PropertyTypeDetail({ type }: { type: PropertyTypeRow }) {
    const rows: [string, string][] = [
        ['Name', type.name],
        ['Category', type.category_label],
        ['Slug', type.slug],
        ['In use', `${type.properties_count} propert${type.properties_count === 1 ? 'y' : 'ies'}`],
    ];

    return (
        <dl className="grid gap-3 py-2 text-sm">
            {rows.map(([label, value]) => (
                <div key={label} className="grid grid-cols-[7rem_1fr] gap-2">
                    <dt className="text-muted-foreground">{label}</dt>
                    <dd className="min-w-0 break-words">{value}</dd>
                </div>
            ))}
            {type.description && (
                <div className="grid grid-cols-[7rem_1fr] gap-2">
                    <dt className="text-muted-foreground">Description</dt>
                    <dd className="min-w-0 break-words">{type.description}</dd>
                </div>
            )}
        </dl>
    );
}

/**
 * Name, category, description and display order — shared by create and edit.
 *
 * `idPrefix` keeps the two sheets' ids distinct: both can be mounted at once,
 * and duplicate ids would break the `<label htmlFor>` pairing.
 */
function PropertyTypeFields({
    idPrefix,
    type,
    errors,
}: {
    idPrefix: string;
    type?: PropertyTypeRow;
    errors: Record<string, string>;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-name`}>Name</Label>
                <Input
                    id={`${idPrefix}-name`}
                    name="name"
                    defaultValue={type?.name ?? ''}
                    placeholder="Serviced Apartment"
                    required
                />
                <FieldError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-category`}>Category</Label>
                <Select
                    name="category"
                    defaultValue={type?.category ?? 'residential'}
                >
                    <SelectTrigger id={`${idPrefix}-category`}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {CATEGORIES.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <FieldError message={errors.category} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-description`}>
                    Description (optional)
                </Label>
                <Input
                    id={`${idPrefix}-description`}
                    name="description"
                    defaultValue={type?.description ?? ''}
                />
                <FieldError message={errors.description} />
            </div>
        </>
    );
}

PropertyTypesIndex.layout = {
    breadcrumbs: [{ title: 'Properties' }, { title: 'Property types' }],
};