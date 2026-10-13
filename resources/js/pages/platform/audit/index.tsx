import { Head, router } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    Building2,
    Download,
    ListFilter,
    Search,
    Shield,
    User,
} from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import * as auditRoutes from '@/routes/platform/audit';

type Causer = { id: number; name: string; email: string } | null;
type Subject = { type: string; id: number } | null;

type AuditRow = {
    id: number;
    created_at: string;
    event: string | null;
    log_name: string | null;
    description: string | null;
    ip_address: string | null;
    causer: Causer;
    subject: Subject;
};

type Props = {
    audits: {
        data: AuditRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: Record<string, string>;
    sort: { column: string | null; direction: 'asc' | 'desc' };
    canExport: boolean;
    /** The row the URL points at, when it is not on the current page. */
    focusedAudit?: AuditRow | null;
    options: {
        events: string[];
        subjectTypes: Record<string, string>;
        /** Optional server-side: absent until the filters overlay asks. */
        organizations?: { id: string; name: string }[];
    };
};

/** Debounce so a fast typist issues one request, not one per keystroke. */
const SEARCH_DEBOUNCE_MS = 350;

/**
 * The event vocabulary is drawn from a seeded enum, so the glyphs are chosen
 * from a closed set and a `—` covers "no event was recorded".
 */
const EVENT_TONES: Record<string, string> = {
    created: 'bg-status-success/12 text-status-success',
    updated: 'bg-status-warning/12 text-status-warning',
    deleted: 'bg-status-danger/12 text-status-danger',
};

export default function AdminAuditIndex({
    audits,
    filters,
    sort,
    canExport,
    focusedAudit,
    options,
}: Props) {
    const page = usePage();
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

    const viewedId = Number(query.get('audit'));
    const isViewOpen = Number.isFinite(viewedId) && viewedId > 0;
    const viewedAudit = isViewOpen
        ? (audits.data.find((audit) => audit.id === viewedId) ?? focusedAudit)
        : undefined;

    // `query.has()` is true even for an empty value, and closing navigates to
    // `filters=` — so comparing against '' is what actually closes the sheet.
    const isFiltersOpen =
        query.get('filters') !== null && query.get('filters') !== '';

    /** The current filter set, as query params. */
    function currentFilters(): Record<string, string> {
        return {
            search: filters.search ?? '',
            actor: filters.actor ?? '',
            event: filters.event ?? '',
            subject_type: filters.subject_type ?? '',
            tenant_id: filters.tenant_id ?? '',
            date_from: filters.date_from ?? '',
            date_to: filters.date_to ?? '',
        };
    }

    /**
     * Changes only the URL, never the result set — opening or closing a sheet.
     * No prop is requested because nothing on the page changes with the URL.
     */
    function navigate(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        router.get(auditRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only: ['audits', 'filters', 'sort'],
        });
    }

    /**
     * Replaces the visible rows, so the table shows a skeleton while in flight.
     * `only` keeps this off the optional organization list.
     */
    function refetchTable(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        setIsTableLoading(true);

        router.get(auditRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only: ['audits', 'filters', 'sort'],
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
                ...currentFilters(),
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
                ...currentFilters(),
                sort: sort.column ?? '',
                direction: sort.direction,
            },
            { replace: true },
        );
    }

    function openRow(audit: AuditRow) {
        setOverlayParam('mode', 'view', {
            replace: true,
            keep: { audit: String(audit.id) },
        });
    }

    function changePage(nextPage: number) {
        refetchTable({
            ...currentFilters(),
            sort: sort.column ?? '',
            direction: sort.direction,
            page: nextPage,
        });
    }

    function applySorting(next: SortingState) {
        const first = next[0];
        refetchTable(
            {
                ...currentFilters(),
                sort: first?.id ?? '',
                direction: first?.desc ? 'desc' : 'asc',
            },
            { replace: true },
        );
    }

    // `options.organizations` is an optional prop: the server does not send it
    // unless asked. Loading is derived from the prop being absent rather than
    // tracked separately, so there is no second source of truth to fall out of
    // sync with the request.
    useEffect(() => {
        if (isFiltersOpen && options.organizations === undefined) {
            router.reload({ only: ['options'] });
        }
    }, [isFiltersOpen, options.organizations]);

    // Live search: local input for responsiveness, debounced server round-trip.
    const [searchDraft, setSearchDraft] = useState(filters.search ?? '');
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        if (searchDraft === (filters.search ?? '')) {
            return;
        }

        const timer = window.setTimeout(() => {
            refetchTable(
                {
                    ...currentFilters(),
                    search: searchDraft,
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
    // is React's documented pattern for derived-from-props state.
    const [serverSearch, setServerSearch] = useState(filters.search ?? '');

    if ((filters.search ?? '') !== serverSearch) {
        setServerSearch(filters.search ?? '');
        setSearchDraft(filters.search ?? '');
    }

    const columns = useMemo<ColumnDef<AuditRow, unknown>[]>(() => {
        const cells: ColumnDef<AuditRow, unknown>[] = [
            {
                id: 'created_at',
                accessorKey: 'created_at',
                header: 'Time',
                cell: ({ row }) => (
                    <span className="block whitespace-nowrap text-xs text-muted-foreground tabular-nums">
                        {new Date(row.original.created_at).toLocaleString()}
                    </span>
                ),
            },
            {
                // Sorting by the actor's name needs a join through a morph, so
                // there is no meaningful server-side sort to expose.
                id: 'causer',
                accessorKey: 'causer',
                header: 'Actor',
                enableSorting: false,
                cell: ({ row }) => {
                    const causer = row.original.causer;

                    if (!causer) {
                        return (
                            <span className="text-muted-foreground flex items-center gap-2 text-xs">
                                <Shield className="size-4" />
                                System
                            </span>
                        );
                    }

                    return (
                        <div className="flex min-w-0 items-center gap-2">
                            <User className="size-4 shrink-0 text-muted-foreground" />
                            <div className="min-w-0">
                                <div className="truncate text-sm">
                                    {causer.name}
                                </div>
                                <div className="truncate text-xs text-muted-foreground">
                                    {causer.email}
                                </div>
                            </div>
                        </div>
                    );
                },
            },
            {
                id: 'event',
                accessorKey: 'event',
                header: 'Action',
                cell: ({ row }) => {
                    const event = row.original.event;

                    if (!event) {
                        return <span className="text-xs">—</span>;
                    }

                    return (
                        <span
                            className={`inline-block rounded px-2 py-0.5 text-xs capitalize ${
                                EVENT_TONES[event] ??
                                'bg-muted text-muted-foreground'
                            }`}
                        >
                            {event}
                        </span>
                    );
                },
            },
            {
                id: 'subject',
                accessorKey: 'subject',
                header: 'Resource',
                enableSorting: false,
                cell: ({ row }) => {
                    const subject = row.original.subject;

                    if (!subject) {
                        return <span className="text-xs">—</span>;
                    }

                    return (
                        <span
                            title={`${subject.type} #${subject.id}`}
                            className="block max-w-40 truncate text-xs sm:max-w-56"
                        >
                            {subject.type} #{subject.id}
                        </span>
                    );
                },
            },
            {
                id: 'description',
                accessorKey: 'description',
                header: 'Description',
                enableSorting: false,
                cell: ({ row }) => (
                    <span
                        title={row.original.description ?? ''}
                        className="block max-w-64 truncate text-xs sm:max-w-96"
                    >
                        {row.original.description ?? '—'}
                    </span>
                ),
            },
            {
                id: 'ip_address',
                accessorKey: 'ip_address',
                header: 'IP',
                enableSorting: false,
                cell: ({ row }) => (
                    <span className="block whitespace-nowrap text-xs text-muted-foreground tabular-nums">
                        {row.original.ip_address ?? '—'}
                    </span>
                ),
            },
        ];

        return cells;
    }, []);

    const sorting = useMemo<SortingState>(
        () =>
            sort.column
                ? [{ id: sort.column, desc: sort.direction === 'desc' }]
                : [],
        [sort.column, sort.direction],
    );

    /**
     * Exports via a real navigation, not `router.visit()` and not `fetch()`.
     * The endpoint answers with a file attachment, so an Inertia visit has
     * nothing to render, and a fetch-built blob would drop the session cookie.
     * A hidden form GET is the only one of the three that keeps the cookie and
     * lets the browser download.
     */
    function downloadExport() {
        const url = new URL(auditRoutes.exportMethod().url, window.location.origin);

        for (const [key, value] of Object.entries(currentFilters())) {
            if (value !== '') {
                url.searchParams.set(key, value);
            }
        }

        if (sort.column) {
            url.searchParams.set('sort', sort.column);
            url.searchParams.set('direction', sort.direction);
        }

        const form = document.createElement('form');
        form.method = 'GET';
        form.action = url.pathname + url.search;
        form.hidden = true;
        document.body.appendChild(form);
        form.submit();
        form.remove();

        setOverlayParam('export', null, { replace: true });
    }

    return (
        <>
            <Head title="Audit log" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-3">
                    <Heading
                        title="Audit log"
                        description="Every action across all organizations."
                    />
                </div>

                {/*
                 * One card = the table's frame. Search/filters live in a header
                 * band above the rows rather than in their own floating card
                 * (that produced a box-in-box). Export sits here because it
                 * acts on this table's result set.
                 *
                 * The audit log is read-only, so there is no page-level CTA at
                 * all — the header band is the only row of controls.
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
                                placeholder="Search description..."
                                aria-label="Search audit entries"
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
                        {canExport && (
                            <ActionIconButton
                                icon={<Download />}
                                label="Export CSV"
                                color="teal"
                                onClick={() => setOverlayParam('export', '1')}
                            />
                        )}
                    </div>
                    <DataTable
                        columns={columns}
                        data={audits.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        onSortingChange={applySorting}
                        loading={isTableLoading}
                        loadingRows={15}
                        onRowClick={openRow}
                        empty="No audit entries match your filters."
                    />
                </Card>

                {audits.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {audits.current_page} of {audits.last_page} (
                            {audits.total} entries)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={audits.current_page <= 1}
                                onClick={() =>
                                    changePage(audits.current_page - 1)
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={audits.current_page >= audits.last_page}
                                onClick={() =>
                                    changePage(audits.current_page + 1)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <ResponsiveOverlay
                open={isViewOpen && viewedAudit !== undefined}
                onOpenChange={(open) => {
                    if (!open) {
                        closeRowSheet();
                    }
                }}
                title={viewedAudit?.event ?? 'Audit entry'}
                description={
                    !viewedAudit
                        ? 'This entry is not on the current page of results.'
                        : 'The full record for this action.'
                }
                footer={
                    <OverlayFooter onCancel={closeRowSheet} />
                }
            >
                {viewedAudit && (
                    <div className="grid gap-4 py-2">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <span>
                                {new Date(
                                    viewedAudit.created_at,
                                ).toLocaleString()}
                            </span>
                            {viewedAudit.event && (
                                <span
                                    className={`rounded px-2 py-0.5 text-xs capitalize ${
                                        EVENT_TONES[viewedAudit.event] ??
                                        'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {viewedAudit.event}
                                </span>
                            )}
                        </div>

                        <dl className="grid gap-3 text-sm">
                            {[
                                [
                                    'Actor',
                                    viewedAudit.causer
                                        ? `${viewedAudit.causer.name} (${viewedAudit.causer.email})`
                                        : 'System',
                                ],
                                [
                                    'Resource',
                                    viewedAudit.subject
                                        ? `${viewedAudit.subject.type} #${viewedAudit.subject.id}`
                                        : '—',
                                ],
                                ['Category', viewedAudit.log_name ?? '—'],
                                ['IP address', viewedAudit.ip_address ?? '—'],
                            ].map(([label, value]) => (
                                <div
                                    key={label}
                                    className="grid grid-cols-[8rem_1fr] gap-2"
                                >
                                    <dt className="text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="min-w-0 break-words">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                        </dl>

                        <div className="grid gap-2">
                            <div className="text-sm font-medium">Description</div>
                            <p className="text-muted-foreground text-sm whitespace-pre-wrap">
                                {viewedAudit.description ?? 'No description.'}
                            </p>
                        </div>
                    </div>
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
                description="Narrow the log by actor, action or date."
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
                                            actor: '',
                                            event: '',
                                            subject_type: '',
                                            tenant_id: '',
                                            date_from: '',
                                            date_to: '',
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
                        <Label htmlFor="filter-search">Search</Label>
                        <Input
                            id="filter-search"
                            value={searchDraft}
                            onChange={(event) =>
                                setSearchDraft(event.target.value)
                            }
                            placeholder="Description..."
                        />
                        {errors.search && (
                            <p className="text-xs text-destructive">
                                {errors.search}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="filter-actor">Actor</Label>
                        <Input
                            id="filter-actor"
                            value={filters.actor ?? ''}
                            onChange={(event) =>
                                refetchTable(
                                    {
                                        ...currentFilters(),
                                        actor: event.target.value,
                                        sort: sort.column ?? '',
                                        direction: sort.direction,
                                        filters: '1',
                                    },
                                    { replace: true },
                                )
                            }
                            placeholder="Name or email"
                        />
                    </div>

                    {/*
                     * Event and subject type have open-ended vocabularies —
                     * they grow with whatever code writes to the log — so they
                     * stay as Selects with a placeholder. A radio group only
                     * suits a small, fixed set; see the users filters.
                     */}
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="filter-event">Action</Label>
                            <Select
                                value={filters.event ?? ''}
                                onValueChange={(value) =>
                                    refetchTable(
                                        {
                                            ...currentFilters(),
                                            event: value,
                                            sort: sort.column ?? '',
                                            direction: sort.direction,
                                            filters: '1',
                                        },
                                        { replace: true },
                                    )
                                }
                            >
                                <SelectTrigger id="filter-event">
                                    <SelectValue placeholder="Any action" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.events.map((event) => (
                                        <SelectItem key={event} value={event}>
                                            {event}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {/* Radix reserves "" for the placeholder, so this
                                hidden field is what submits "any". */}
                            <input
                                type="hidden"
                                name="event"
                                value={filters.event ?? ''}
                                readOnly
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="filter-subject_type">
                                Resource
                            </Label>
                            <Select
                                value={filters.subject_type ?? ''}
                                onValueChange={(value) =>
                                    refetchTable(
                                        {
                                            ...currentFilters(),
                                            subject_type: value,
                                            sort: sort.column ?? '',
                                            direction: sort.direction,
                                            filters: '1',
                                        },
                                        { replace: true },
                                    )
                                }
                            >
                                <SelectTrigger id="filter-subject_type">
                                    <SelectValue placeholder="Any resource" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(
                                        options.subjectTypes,
                                    ).map(([type, text]) => (
                                        <SelectItem key={type} value={type}>
                                            {text}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <input
                                type="hidden"
                                name="subject_type"
                                value={filters.subject_type ?? ''}
                                readOnly
                            />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="filter-organization">
                            Organization
                        </Label>
                        {options.organizations === undefined ? (
                            <Input
                                disabled
                                aria-label="Loading organizations"
                                placeholder="Loading organizations…"
                            />
                        ) : (
                            <>
                                <Select
                                    value={filters.tenant_id ?? ''}
                                    onValueChange={(value) =>
                                        refetchTable(
                                            {
                                                ...currentFilters(),
                                                tenant_id: value,
                                                sort: sort.column ?? '',
                                                direction: sort.direction,
                                                filters: '1',
                                            },
                                            { replace: true },
                                        )
                                    }
                                >
                                    <SelectTrigger id="filter-organization">
                                        <SelectValue placeholder="All organizations" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.organizations.map((org) => (
                                            <SelectItem
                                                key={org.id}
                                                value={String(org.id)}
                                            >
                                                <span className="flex items-center gap-2">
                                                    <Building2 className="size-4 text-muted-foreground" />
                                                    {org.name}
                                                </span>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <input
                                    type="hidden"
                                    name="tenant_id"
                                    value={filters.tenant_id ?? ''}
                                    readOnly
                                />
                            </>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label>Date</Label>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="filter-date_from"
                                    className="text-xs font-normal text-muted-foreground"
                                >
                                    From
                                </Label>
                                <Input
                                    id="filter-date_from"
                                    type="date"
                                    value={filters.date_from ?? ''}
                                    onChange={(event) =>
                                        refetchTable(
                                            {
                                                ...currentFilters(),
                                                date_from: event.target.value,
                                                sort: sort.column ?? '',
                                                direction: sort.direction,
                                                filters: '1',
                                            },
                                            { replace: true },
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="filter-date_to"
                                    className="text-xs font-normal text-muted-foreground"
                                >
                                    To
                                </Label>
                                <Input
                                    id="filter-date_to"
                                    type="date"
                                    value={filters.date_to ?? ''}
                                    onChange={(event) =>
                                        refetchTable(
                                            {
                                                ...currentFilters(),
                                                date_to: event.target.value,
                                                sort: sort.column ?? '',
                                                direction: sort.direction,
                                                filters: '1',
                                            },
                                            { replace: true },
                                        )
                                    }
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={query.get('export') === '1'}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('export', null, { replace: true });
                    }
                }}
                title="Export audit log"
                description="Download every entry matching the current filters."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('export', null, { replace: true })
                        }
                        actions={[
                            {
                                label: 'Download CSV',
                                icon: <Download className="size-4" />,
                                onClick: () => downloadExport(),
                            },
                        ]}
                    />
                }
            >
                <div className="space-y-3 py-2 text-sm">
                    <div className="rounded-lg border border-border-strong bg-background p-3">
                        <div className="font-medium">All matching entries</div>
                        <div className="text-muted-foreground mt-1 text-xs">
                            Every audit entry matching the current filters
                            {Object.values(currentFilters()).some((v) => v !== '')
                                ? '.'
                                : ' (no filters are active).'}{' '}
                            Ignores pagination — {audits.total} entr
                            {audits.total === 1 ? 'y' : 'ies'} in total.
                        </div>
                    </div>
                </div>
            </ResponsiveOverlay>
        </>
    );
}

AdminAuditIndex.layout = {
    breadcrumbs: [{ title: 'Audit log' }],
};
