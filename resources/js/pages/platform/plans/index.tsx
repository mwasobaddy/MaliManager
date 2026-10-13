import { Head, router } from '@inertiajs/react';
import { Form, usePage } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import { Layers, ListFilter, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { StatusIcon, StatusPowerToggle } from '@/components/status-cell';
import type { StatusTone } from '@/components/status-cell';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import * as planRoutes from '@/routes/platform/plans';

type PlanRow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    currency: string;
    price: number;
    properties_limit: number | null;
    units_limit: number | null;
    has_dedicated_db: boolean;
    has_custom_domain: boolean;
    has_email_notifications: boolean;
    has_sms_notifications: boolean;
    features: string[];
    is_active: boolean;
    sort_order: number;
    organizations_count: number;
};

type Props = {
    plans: {
        data: PlanRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: { search: string; status: string };
    sort: { column: string | null; direction: 'asc' | 'desc' };
    /**
     * The row the URL points at, when it is not on the current page. The
     * controller only fills this in for a `?plan=` id the paginated result set
     * does not already contain.
     */
    focusedPlan?: PlanRow | null;
};

const STATUS_TONES: Record<string, StatusTone> = {
    active: 'success',
    inactive: 'warning',
};

/** Debounce so a fast typist issues one request, not one per keystroke. */
const SEARCH_DEBOUNCE_MS = 350;

export default function PlansIndex({
    plans,
    filters,
    sort,
    focusedPlan,
}: Props) {
    const page = usePage();
    const permissions = (page.props.auth?.permissions ?? []) as string[];
    const canCreate = permissions.includes('plan.create');
    const canEdit = permissions.includes('plan.edit');
    const canDelete = permissions.includes('plan.delete');

    const errors = (page.props.errors ?? {}) as Record<string, string>;

    const [isTableLoading, setIsTableLoading] = useState(false);

    // Read query params off Inertia's page.url, NOT window.location. Inertia
    // pushes history *after* React renders, so window.location is stale during
    // render and nothing re-renders afterwards — the overlay would not open
    // until a full page load. page.url is part of the reactive page object.
    const query = useMemo(() => {
        const index = page.url.indexOf('?');

        return new URLSearchParams(index === -1 ? '' : page.url.slice(index));
    }, [page.url]);

    const viewedId = Number(query.get('plan'));
    const isViewOpen = Number.isFinite(viewedId) && viewedId > 0;
    // Prefer the row already on this page; fall back to the one the server
    // fetched for the URL. That fallback is what makes a shared link to a row
    // on another page work, and is why this overlay does not need its own page.
    const viewedPlan = isViewOpen
        ? (plans.data.find((plan) => plan.id === viewedId) ?? focusedPlan)
        : undefined;

    // One row sheet with switchable content rather than a sheet per action:
    // stacking Radix modals fights over the focus trap and double-dims the page.
    //
    // `confirm-delete` is a fourth mode rather than a separate second overlay:
    // the first Delete press only asks whether you mean it, and the password
    // prompt appears inside the same sheet once you have said yes.
    type RowMode = 'view' | 'edit' | 'delete' | 'confirm-delete';
    const rowMode = (query.get('mode') as RowMode | null) ?? 'view';
    const isCreateOpen = query.get('create') === '1';

    // `query.has()` is true even for an empty value, and closing navigates to
    // `filters=` — so comparing against '' is what actually closes the sheet.
    const isFiltersOpen =
        query.get('filters') !== null && query.get('filters') !== '';

    /**
     * Changes only the URL, never the result set — opening or closing a sheet.
     * No prop is requested because nothing on the page changes with the URL.
     */
    function navigate(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        router.get(planRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only: ['plans', 'filters', 'sort'],
        });
    }

    /**
     * Replaces the visible rows, so the table shows a skeleton while in flight.
     */
    function refetchTable(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        setIsTableLoading(true);

        router.get(planRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only: ['plans', 'filters', 'sort'],
            onFinish: () => setIsTableLoading(false),
        });
    }

    // Overlays push history so Back closes them; closing replaces so Back does
    // not walk back through every sheet the user opened.
    function setOverlayParam(
        key: string,
        value: string | null,
        options?: {
            replace?: boolean;
            /** Extra params to carry through, e.g. the row being opened. */
            keep?: Record<string, string>;
        },
    ) {
        navigate(
            {
                search: filters.search,
                status: filters.status,
                sort: sort.column ?? '',
                direction: sort.direction,
                ...(options?.keep ?? {}),
                [key]: value ?? '',
            },
            { replace: options?.replace },
        );
    }

    /** Close the row sheet, dropping both the row id and the mode. */
    function closeRowSheet() {
        navigate(
            {
                search: filters.search,
                status: filters.status,
                sort: sort.column ?? '',
                direction: sort.direction,
            },
            { replace: true },
        );
    }

    function setStatus(plan: PlanRow, isActive: boolean) {
        router.patch(
            planRoutes.status(plan.id),
            { is_active: isActive },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    // Keep the status filter honest: if you deactivate while
                    // filtering by "active", the row no longer belongs.
                    if (filters.status && (filters.status === 'active') !== isActive) {
                        refetchTable(
                            {
                                search: filters.search,
                                status: filters.status,
                                sort: sort.column ?? '',
                                direction: sort.direction,
                            },
                            { replace: true },
                        );
                    }
                },
            },
        );
    }

    /**
     * Opens the row overlay in `mode`. Every mode is available on every
     * viewport — on a phone the overlay is a bottom drawer rather than a side
     * sheet, but nothing has to be reached by redirecting to a separate page.
     */
    function openRow(plan: PlanRow, mode: RowMode) {
        setOverlayParam('mode', mode, {
            replace: mode === 'view',
            keep: { plan: String(plan.id) },
        });
    }

    function changePage(nextPage: number) {
        refetchTable({
            search: filters.search,
            status: filters.status,
            sort: sort.column ?? '',
            direction: sort.direction,
            page: nextPage,
        });
    }

    function applySorting(next: SortingState) {
        const first = next[0];
        refetchTable(
            {
                search: filters.search,
                status: filters.status,
                sort: first?.id ?? '',
                direction: first?.desc ? 'desc' : 'asc',
            },
            { replace: true },
        );
    }

    // Live search: local input for responsiveness, debounced server round-trip.
    const [searchDraft, setSearchDraft] = useState(filters.search);
    const firstRender = useRef(true);

    useEffect(() => {
        // Never fire on mount — the server already sent the filtered result.
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
    // clearing the search. Adjusting during render (rather than in an effect)
    // is React's documented pattern for derived-from-props state, and avoids
    // the extra render pass an effect would cost.
    const [serverSearch, setServerSearch] = useState(filters.search);

    if (filters.search !== serverSearch) {
        setServerSearch(filters.search);
        setSearchDraft(filters.search);
    }

    const columns = useMemo<ColumnDef<PlanRow, unknown>[]>(() => {
        const cells: ColumnDef<PlanRow, unknown>[] = [
            {
                id: 'name',
                accessorKey: 'name',
                header: 'Plan',
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <Layers className="size-4 shrink-0 text-muted-foreground" />
                        <div className="min-w-0">
                            <div className="truncate font-medium">
                                {row.original.name}
                            </div>
                            <div className="truncate text-xs text-muted-foreground">
                                {row.original.slug}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'price',
                accessorKey: 'price',
                header: 'Price',
                meta: { align: 'right' },
                cell: ({ row }) => (
                    <span className="block whitespace-nowrap text-right tabular-nums">
                        {row.original.currency}{' '}
                        {row.original.price.toLocaleString()}
                    </span>
                ),
            },
            {
                // Two numbers in one cell, so there is no single column to sort
                // on and a sort header here would be a lie.
                id: 'limits',
                header: 'Limits',
                enableSorting: false,
                cell: ({ row }) => (
                    <div className="whitespace-nowrap text-xs">
                        <div>
                            {row.original.properties_limit ?? '∞'} properties
                        </div>
                        <div className="text-muted-foreground">
                            {row.original.units_limit ?? '∞'} units
                        </div>
                    </div>
                ),
            },
            {
                // A count with no useful ordering, and the full list would wrap
                // the row. The tooltip carries every feature.
                id: 'features',
                header: 'Features',
                enableSorting: false,
                cell: ({ row }) => {
                    const features = row.original.features;

                    if (features.length === 0) {
                        return <span className="text-xs">—</span>;
                    }

                    return (
                        <span
                            title={features.join('\n')}
                            className="block max-w-40 truncate text-xs sm:max-w-56"
                        >
                            {features.length === 1
                                ? features[0]
                                : `${features.length} features`}
                        </span>
                    );
                },
            },
            {
                id: 'organizations',
                accessorKey: 'organizations_count',
                header: 'Organizations',
                meta: { align: 'right' },
                cell: ({ row }) => (
                    <span className="block text-right tabular-nums">
                        {row.original.organizations_count}
                    </span>
                ),
            },
            {
                id: 'status',
                accessorKey: 'is_active',
                header: 'Status',
                meta: { align: 'center' },
                cell: ({ row }) => (
                    // Reports state only. The control that changes it is the
                    // Power toggle in the action cell, so this column never
                    // looks editable and the two are not confused.
                    <div className="flex items-center justify-center">
                        <StatusIcon
                            tone={
                                row.original.is_active
                                    ? STATUS_TONES.active
                                    : STATUS_TONES.inactive
                            }
                            label={row.original.is_active ? 'Active' : 'Inactive'}
                        />
                    </div>
                ),
            },
            {
                id: 'actions',
                header: 'Actions',
                enableSorting: false,
                cell: ({ row }) => {
                    const plan = row.original;

                    return (
                        <div
                            className="flex justify-end gap-1.5"
                            // The whole row opens the detail sheet; interactive
                            // controls inside it must not also trigger that.
                            onClick={(event) => event.stopPropagation()}
                        >
                            {canEdit && (
                                <StatusPowerToggle
                                    status={
                                        plan.is_active ? 'active' : 'inactive'
                                    }
                                    activeLabel={`Deactivate ${plan.name}`}
                                    inactiveLabel={`Reactivate ${plan.name}`}
                                    // An inactive plan is off sale, not broken,
                                    // so it reads as a warning rather than a
                                    // danger the way a suspended user does.
                                    inactiveTone="warning"
                                    onClick={() =>
                                        setStatus(plan, !plan.is_active)
                                    }
                                />
                            )}
                            {canEdit && (
                                <ActionIconButton
                                    icon={<Pencil />}
                                    label={`Edit ${plan.name}`}
                                    color="edit"
                                    onClick={() => openRow(plan, 'edit')}
                                />
                            )}
                            {canDelete && (
                                <ActionIconButton
                                    icon={<Trash2 />}
                                    label={`Delete ${plan.name}`}
                                    color="danger"
                                    onClick={() => openRow(plan, 'delete')}
                                />
                            )}
                        </div>
                    );
                },
            },
        ];

        return cells;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [canEdit, canDelete, filters.search, filters.status, sort.column, sort.direction]);

    const sorting = useMemo<SortingState>(
        () =>
            sort.column
                ? [{ id: sort.column, desc: sort.direction === 'desc' }]
                : [],
        [sort.column, sort.direction],
    );

    return (
        <>
            <Head title="Plans" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-3">
                    <Heading
                        title="Plans"
                        description="Subscription tiers and their limits."
                    />
                    {canCreate && (
                        <LabelledIconButton
                            icon={<Plus />}
                            label="New plan"
                            tooltip="Create plan"
                            color="primary"
                            onClick={() => setOverlayParam('create', '1')}
                            className="max-w-20 sm:max-w-24"
                        />
                    )}
                </div>

                {/*
                 * One card = the table's frame. Search/filters live in a header
                 * band above the rows rather than in their own floating card
                 * (that produced a box-in-box). "New plan" is a page-level CTA
                 * and stays in the page header.
                 *
                 * `gap-0 py-0` is what makes the band flush with the card edge:
                 * the Card's own padding would otherwise inset the search row
                 * relative to the table below it.
                 */}
                <Card className="gap-0 py-0">
                    <div className="flex flex-wrap items-center gap-2 border-b border-border-strong p-4">
                        <div className="relative min-w-56 flex-1">
                            <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={searchDraft}
                                onChange={(event) =>
                                    setSearchDraft(event.target.value)
                                }
                                placeholder="Search name, slug..."
                                aria-label="Search plans"
                                // shadcn Input is bg-transparent, which leaves
                                // the field defined by a 1.2:1 border alone. On
                                // a card surface it needs a fill + a stronger
                                // border to be readable in both schemes.
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
                    </div>
                    <DataTable
                        columns={columns}
                        data={plans.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        onSortingChange={applySorting}
                        loading={isTableLoading}
                        loadingRows={15}
                        onRowClick={(plan) => openRow(plan, 'view')}
                        empty="No plans match your filters."
                    />
                </Card>

                {plans.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {plans.current_page} of {plans.last_page} (
                            {plans.total} plans)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={plans.current_page <= 1}
                                onClick={() =>
                                    changePage(plans.current_page - 1)
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={plans.current_page >= plans.last_page}
                                onClick={() =>
                                    changePage(plans.current_page + 1)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            {/* The row sheet: view, edit and delete share one overlay. */}
            <ResponsiveOverlay
                open={isViewOpen && viewedPlan !== undefined}
                onOpenChange={(open) => {
                    if (!open) {
                        closeRowSheet();
                    }
                }}
                title={viewedPlan?.name ?? 'Plan'}
                description={
                    !viewedPlan
                        ? 'This plan is not on the current page of results.'
                        : rowMode === 'delete'
                          ? 'This soft-deletes the tier. Existing organizations keep it.'
                          : rowMode === 'edit'
                            ? 'Update pricing, limits and enabled features.'
                            : 'Details for this plan.'
                }
                footer={
                    rowMode === 'delete' && viewedPlan ? (
                        <OverlayFooter
                            onCancel={closeRowSheet}
                            actions={[
                                {
                                    label: 'Continue to delete',
                                    tone: 'danger-ghost',
                                    icon: <Trash2 className="size-4" />,
                                    onClick: () =>
                                        setOverlayParam('mode', 'confirm-delete', {
                                            keep: { plan: String(viewedPlan.id) },
                                        }),
                                },
                            ]}
                        />
                    ) : rowMode === 'confirm-delete' && viewedPlan ? (
                        <OverlayFooter
                            onCancel={() => openRow(viewedPlan, 'delete')}
                            actions={[
                                {
                                    label: 'Delete plan',
                                    form: 'plan-delete-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    ) : rowMode === 'edit' && viewedPlan ? (
                        <OverlayFooter
                            onCancel={closeRowSheet}
                            actions={[
                                {
                                    label: 'Save changes',
                                    form: 'plan-edit-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    ) : (
                        <OverlayFooter
                            actions={[
                                {
                                    label: 'Edit',
                                    tone: 'secondary',
                                    icon: <Pencil className="size-4" />,
                                    onClick: () =>
                                        viewedPlan &&
                                        openRow(viewedPlan, 'edit'),
                                    ...(canEdit ? {} : { disabled: true }),
                                },
                            ]}
                        />
                    )
                }
            >
                {viewedPlan &&
                    (rowMode === 'edit' ? (
                        <Form
                            key={viewedPlan.id}
                            id="plan-edit-form"
                            method="patch"
                            action={planRoutes.update(viewedPlan.id)}
                            onSuccess={() => closeRowSheet()}
                        >
                            <PlanFormFields plan={viewedPlan} errors={errors} />
                        </Form>
                    ) : rowMode === 'delete' ? (
                        <p className="text-sm">
                            Deleting{' '}
                            <span className="font-medium">
                                {viewedPlan.name}
                            </span>{' '}
                            takes it off sale, so no new organization can be
                            put on it. Organizations already on it are
                            unaffected and the tier can be restored.
                        </p>
                    ) : rowMode === 'confirm-delete' ? (
                        <Form
                            id="plan-delete-form"
                            method="delete"
                            action={planRoutes.destroy(viewedPlan.id)}
                            onSuccess={() => closeRowSheet()}
                            className="grid gap-3"
                        >
                            <p className="text-sm">
                                This soft-deletes{' '}
                                <span className="font-medium">
                                    {viewedPlan.name}
                                </span>
                                . Confirm with your password to continue.
                            </p>

                            <div className="grid gap-2">
                                <Label htmlFor="delete-password">
                                    Confirm with your password
                                </Label>
                                <Input
                                    id="delete-password"
                                    name="password"
                                    type="password"
                                    autoComplete="current-password"
                                    required
                                />
                                {errors.password && (
                                    <p className="text-xs text-destructive">
                                        {errors.password}
                                    </p>
                                )}
                            </div>
                        </Form>
                    ) : (
                        <PlanDetail plan={viewedPlan} />
                    ))}
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isCreateOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('create', null, { replace: true });
                    }
                }}
                title="Create plan"
                description="Define pricing, limits and enabled features for this tier."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('create', null, { replace: true })
                        }
                        actions={[
                            {
                                label: 'Create plan',
                                form: 'plan-create-form',
                                type: 'submit',
                            },
                        ]}
                    />
                }
            >
                <Form
                    id="plan-create-form"
                    method="post"
                    action={planRoutes.store()}
                    onSuccess={() =>
                        setOverlayParam('create', null, { replace: true })
                    }
                >
                    <PlanFormFields errors={errors} />
                </Form>
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isFiltersOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('filters', null, { replace: true });
                    }
                }}
                title="Filters"
                description="Narrow the list of plans."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('filters', null, { replace: true })
                        }
                        cancelLabel="Close"
                        actions={[
                            {
                                label: 'Clear all',
                                tone: 'secondary',
                                onClick: () =>
                                    refetchTable(
                                        {
                                            search: '',
                                            status: '',
                                            sort: sort.column ?? '',
                                            direction: sort.direction,
                                        },
                                        { replace: true },
                                    ),
                            },
                        ]}
                    />
                }
            >
                <div className="grid gap-4 py-2">
                    <div className="grid gap-2">
                        <Label>Status</Label>
                        {/* Radix Select reserves the empty value for its
                            placeholder, so "Any status" is not expressible as
                            an item. Three options is also fewer taps than
                            opening a dropdown. */}
                        <RadioGroup
                            value={filters.status || 'any'}
                            onValueChange={(value) =>
                                refetchTable(
                                    {
                                        search: filters.search,
                                        status:
                                            value === 'any' ? '' : value,
                                        sort: sort.column ?? '',
                                        direction: sort.direction,
                                        filters: '1',
                                    },
                                    { replace: true },
                                )
                            }
                            className="grid gap-2"
                        >
                            {[
                                ['any', 'Any status'],
                                ['active', 'Active'],
                                ['inactive', 'Inactive'],
                            ].map(([value, text]) => (
                                <div
                                    key={value}
                                    className="flex items-center gap-2"
                                >
                                    <RadioGroupItem
                                        id={`filter-status-${value}`}
                                        value={value}
                                    />
                                    <Label
                                        htmlFor={`filter-status-${value}`}
                                        className="cursor-pointer"
                                    >
                                        {text}
                                    </Label>
                                </div>
                            ))}
                        </RadioGroup>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="filter-search">Search</Label>
                        <Input
                            id="filter-search"
                            value={searchDraft}
                            onChange={(event) =>
                                setSearchDraft(event.target.value)
                            }
                            placeholder="Name, slug..."
                        />
                        {errors.search && (
                            <p className="text-xs text-destructive">
                                {errors.search}
                            </p>
                        )}
                    </div>
                </div>
            </ResponsiveOverlay>
        </>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="text-xs text-destructive">{message}</p>;
}

/** Read-only summary of a plan, for the row sheet's default mode. */
function PlanDetail({ plan }: { plan: PlanRow }) {
    const flags: [string, boolean][] = [
        ['Dedicated database', plan.has_dedicated_db],
        ['Custom domain', plan.has_custom_domain],
        ['Email notifications', plan.has_email_notifications],
        ['SMS notifications', plan.has_sms_notifications],
    ];

    return (
        <div className="grid gap-4 py-2">
            {plan.description && (
                <p className="text-sm text-muted-foreground">
                    {plan.description}
                </p>
            )}

            <dl className="grid gap-3 text-sm">
                {[
                    ['Slug', plan.slug],
                    ['Price', `${plan.currency} ${plan.price.toLocaleString()}`],
                    [
                        'Properties limit',
                        plan.properties_limit === null
                            ? 'Unlimited'
                            : String(plan.properties_limit),
                    ],
                    [
                        'Units limit',
                        plan.units_limit === null
                            ? 'Unlimited'
                            : String(plan.units_limit),
                    ],
                    ['Sort order', String(plan.sort_order)],
                    [
                        'Organizations',
                        String(plan.organizations_count),
                    ],
                    ['Status', plan.is_active ? 'Active' : 'Inactive'],
                ].map(([label, value]) => (
                    <div
                        key={label}
                        className="grid grid-cols-[9rem_1fr] gap-2"
                    >
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="min-w-0 break-words">{value}</dd>
                    </div>
                ))}
            </dl>

            <div className="grid gap-2">
                <div className="text-sm font-medium">Included features</div>
                {plan.features.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        None listed.
                    </p>
                ) : (
                    <ul className="text-muted-foreground grid gap-1 text-sm">
                        {plan.features.map((feature) => (
                            <li key={feature}>{feature}</li>
                        ))}
                    </ul>
                )}
            </div>

            <div className="grid gap-2">
                <div className="text-sm font-medium">Capabilities</div>
                <ul className="text-muted-foreground grid gap-1 text-sm">
                    {flags.map(([label, enabled]) => (
                        <li key={label}>
                            {label}: {enabled ? 'yes' : 'no'}
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

/**
 * The create and edit forms share one component.
 *
 * Two of these controls do not serialise themselves, and that is the whole
 * reason this is not plain markup:
 *
 * - The shadcn `Checkbox` renders a `<button>`, so a checked box submits
 *   nothing. Each one is paired with a hidden input carrying "1"/"0", which
 *   the `boolean` rule accepts and which is present whether or not the box is
 *   ticked — that is what makes "false" submittable at all.
 * - `features` is validated as an array but is edited as one-per-line text, so
 *   the textarea is unnamed and the split lines ride as `features[]` inputs.
 *
 * With no features and no name on the textarea, nothing is sent for
 * `features`, which the request treats as nullable.
 */
function PlanFormFields({
    plan,
    errors,
}: {
    plan?: PlanRow;
    errors: Record<string, string>;
}) {
    const [featuresText, setFeaturesText] = useState(
        plan?.features.join('\n') ?? '',
    );
    const [flags, setFlags] = useState({
        is_active: plan?.is_active ?? true,
        has_dedicated_db: plan?.has_dedicated_db ?? false,
        has_custom_domain: plan?.has_custom_domain ?? false,
        has_email_notifications: plan?.has_email_notifications ?? false,
        has_sms_notifications: plan?.has_sms_notifications ?? false,
    });

    const featureLines = featuresText
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);

    const flagFields: [keyof typeof flags, string][] = [
        ['is_active', 'Active (available for new organizations)'],
        ['has_dedicated_db', 'Dedicated database'],
        ['has_custom_domain', 'Custom domain'],
        ['has_email_notifications', 'Email notifications'],
        ['has_sms_notifications', 'SMS notifications'],
    ];

    return (
        <div className="grid gap-3 py-2">
            <div className="grid gap-2">
                <Label htmlFor="plan-name">Name</Label>
                <Input
                    id="plan-name"
                    name="name"
                    defaultValue={plan?.name ?? ''}
                    required
                />
                <FieldError message={errors.name} />
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="plan-slug">Slug</Label>
                    <Input
                        id="plan-slug"
                        name="slug"
                        defaultValue={plan?.slug ?? ''}
                        required
                    />
                    <FieldError message={errors.slug} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="plan-sort_order">Sort order</Label>
                    <Input
                        id="plan-sort_order"
                        name="sort_order"
                        type="number"
                        min={0}
                        defaultValue={String(plan?.sort_order ?? 0)}
                    />
                    <FieldError message={errors.sort_order} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="plan-description">Description</Label>
                <Textarea
                    id="plan-description"
                    name="description"
                    rows={2}
                    defaultValue={plan?.description ?? ''}
                />
                <FieldError message={errors.description} />
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="plan-currency">Currency</Label>
                    <Input
                        id="plan-currency"
                        name="currency"
                        maxLength={3}
                        defaultValue={plan?.currency ?? 'KES'}
                        required
                    />
                    <FieldError message={errors.currency} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="plan-price">Price (per period)</Label>
                    <Input
                        id="plan-price"
                        name="price"
                        type="number"
                        min={0}
                        defaultValue={String(plan?.price ?? 0)}
                        required
                    />
                    <FieldError message={errors.price} />
                </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="plan-properties_limit">
                        Properties limit
                    </Label>
                    <Input
                        id="plan-properties_limit"
                        name="properties_limit"
                        type="number"
                        min={0}
                        placeholder="Unlimited"
                        defaultValue={
                            plan?.properties_limit === null ||
                            plan?.properties_limit === undefined
                                ? ''
                                : String(plan.properties_limit)
                        }
                    />
                    <FieldError message={errors.properties_limit} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="plan-units_limit">Units limit</Label>
                    <Input
                        id="plan-units_limit"
                        name="units_limit"
                        type="number"
                        min={0}
                        placeholder="Unlimited"
                        defaultValue={
                            plan?.units_limit === null ||
                            plan?.units_limit === undefined
                                ? ''
                                : String(plan.units_limit)
                        }
                    />
                    <FieldError message={errors.units_limit} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="plan-features">Features (one per line)</Label>
                <Textarea
                    id="plan-features"
                    rows={4}
                    value={featuresText}
                    onChange={(event) =>
                        setFeaturesText(event.target.value)
                    }
                    placeholder={'SMS alerts\nPriority support'}
                />
                {featureLines.map((feature, index) => (
                    <input
                        key={`${feature}-${index}`}
                        type="hidden"
                        name="features[]"
                        value={feature}
                    />
                ))}
                <FieldError message={errors.features} />
            </div>

            <div className="grid gap-3 pt-1">
                {flagFields.map(([name, text]) => (
                    <div key={name} className="flex items-center gap-2">
                        <Checkbox
                            id={`plan-${name}`}
                            checked={flags[name]}
                            onCheckedChange={(value) =>
                                setFlags((previous) => ({
                                    ...previous,
                                    [name]: Boolean(value),
                                }))
                            }
                        />
                        <Label
                            htmlFor={`plan-${name}`}
                            className="cursor-pointer text-sm font-normal"
                        >
                            {text}
                        </Label>
                        <input
                            type="hidden"
                            name={name}
                            value={flags[name] ? '1' : '0'}
                            readOnly
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}

PlansIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Plans', href: planRoutes.index().url },
    ],
};
