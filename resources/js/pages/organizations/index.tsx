import { Head, router } from '@inertiajs/react';
import { Form, usePage } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    Building2,
    Download,
    ListFilter,
    LogIn,
    Pencil,
    Plus,
    Search,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { StatusIcon  } from '@/components/status-cell';
import type {StatusTone} from '@/components/status-cell';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { useIsMobile } from '@/hooks/use-mobile';
import * as orgRoutes from '@/routes/organizations';

type OrganizationRow = {
    id: number;
    name: string;
    slug: string;
    email: string | null;
    phone: string | null;
    status: string;
    status_label: string;
    plan: string | null;
    plan_id: number | null;
    domain: string | null;
    users_count: number;
    created_at: string | null;
};

type Props = {
    organizations: {
        data: OrganizationRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: { search: string; status: string };
    sort: { column: string | null; direction: 'asc' | 'desc' };
    plans: { id: number; name: string; slug: string }[];
    /** Optional server-side: absent until the create sheet asks for it. */
    owners?: { id: number; name: string; email: string }[];
};

const STATUS_TONES: Record<string, StatusTone> = {
    active: 'success',
    suspended: 'danger',
};

/** Debounce so a fast typist issues one request, not one per keystroke. */
const SEARCH_DEBOUNCE_MS = 350;

export default function OrganizationsIndex({
    organizations,
    filters,
    sort,
    plans,
    owners,
}: Props) {
    const [importErrors, setImportErrors] = useState<
        { row: number; message: string }[]
    >([]);
    const [isTableLoading, setIsTableLoading] = useState(false);
    const page = usePage();
    const permissions =
        ((page.props.auth?.permissions ?? []) as string[]) ?? [];
    const canCreate = permissions.includes('organization.create');
    const canEdit = permissions.includes('organization.edit');
    const canDelete = permissions.includes('organization.delete');
    const canExport = permissions.includes('organization.export');
    const canImport = permissions.includes('organization.import');
    const canImpersonate = permissions.includes('organization.impersonate');

    const errors = (page.props.errors ?? {}) as Record<string, string>;

    const [ownerMode, setOwnerMode] = useState<'existing' | 'new'>('existing');

    // Read query params off Inertia's page.url, NOT window.location. Inertia
    // pushes history *after* React renders, so window.location is stale during
    // render and nothing re-renders afterwards — the overlay would not open
    // until a full page load. page.url is part of the reactive page object.
    const query = useMemo(() => {
        const index = page.url.indexOf('?');

        return new URLSearchParams(index === -1 ? '' : page.url.slice(index));
    }, [page.url]);

    const viewedId = Number(query.get('organization'));
    const isViewOpen = Number.isFinite(viewedId) && viewedId > 0;
    const viewedOrganization = isViewOpen
        ? organizations.data.find(
              (organization) => organization.id === viewedId,
          )
        : undefined;

    const isExportOpen = query.get('export') === '1';
    const isImportOpen = query.get('import') === '1';

    // One row sheet with switchable content rather than a sheet per action:
    // stacking Radix modals fights over the focus trap and double-dims the page.
    type RowMode = 'view' | 'edit' | 'delete';
    const rowMode = (query.get('mode') as RowMode | null) ?? 'view';
    const isCreateOpen = query.get('create') === '1';

    // Downloads must be a real navigation: a plain <a download> would not carry
    // the session cookie, and fetch() would have to read the blob and save it by
    // hand. Triggering a hidden form GET keeps it a normal authenticated request.
    function downloadExportUrl(scope: 'all' | 'page') {
        const url = new URL(orgRoutes.exportMethod().url, window.location.origin);

        if (filters.search) {
            url.searchParams.set('search', filters.search);
        }

        if (filters.status) {
            url.searchParams.set('status', filters.status);
        }

        url.searchParams.set('scope', scope);

        if (scope === 'page') {
            url.searchParams.set('page', String(organizations.current_page));
        }

        const form = document.createElement('form');

        form.method = 'GET';
        form.action = url.pathname + url.search;
        document.body.appendChild(form);
        form.submit();
        form.remove();

        setOverlayParam('export', null, { replace: true });
    }

    // `query.has()` is true even for an empty value, and closing navigates to
    // `filters=` — so comparing against '' is what actually closes the sheet.
    const isFiltersOpen = query.get('filters') !== null && query.get('filters') !== '';

    const isMobile = useIsMobile();

    /**
     * Changes only the URL, never the result set — opening or closing a sheet.
     * No prop is requested because nothing on the page changes with the URL.
     */
    function navigate(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        router.get(orgRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only: ['organizations', 'filters', 'sort'],
        });
    }

    /**
     * Replaces the visible rows, so the table shows a skeleton while in flight.
     * `only` keeps this off the optional owner list and the plan list.
     */
    function refetchTable(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        setIsTableLoading(true);

        router.get(orgRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only: ['organizations', 'filters', 'sort'],
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

    function setStatus(
        organization: OrganizationRow,
        status: 'active' | 'suspended',
    ) {
        router.patch(
            orgRoutes.status(organization.id),
            { status },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    // Keep the status filter honest: if you suspend while
                    // filtering by "active", the row no longer belongs.
if (
                            filters.status &&
                            filters.status !== status
                        ) {
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
     * Open the row sheet in a given mode. On a narrow screen the same actions
     * fall back to their full pages, which have room for the longer forms.
     */
    function openRow(
        organization: OrganizationRow,
        mode: RowMode,
        fallbackUrl: string,
    ) {
        // `orgRoutes.edit()` returns a RouteDefinition; visit needs the href.
        // Only Edit needs a full page on small screens: a form sheet is too
        // cramped there. View and Delete stay as centred dialogs.
        if (isMobile && mode === 'edit') {
            router.visit(fallbackUrl);

            return;
        }

        setOverlayParam('mode', mode, {
            replace: mode === 'view',
            keep: { organization: String(organization.id) },
        });
    }

    function openCreateSheet(fallbackUrl: string) {
        if (isMobile) {
            router.visit(fallbackUrl);

            return;
        }

        setOverlayParam('create', '1');
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

// `owners` is an optional prop: the server does not send it unless asked.
// Fetch it the first time the create sheet opens, and only then. Loading is
// derived from the prop being absent rather than tracked separately, so there
// is no second source of truth to fall out of sync with the request.
useEffect(() => {
    if (isCreateOpen && owners === undefined) {
        router.reload({ only: ['owners'] });
    }
}, [isCreateOpen, owners]);

const isOwnersLoading = isCreateOpen && owners === undefined;

    const columns = useMemo<ColumnDef<OrganizationRow, unknown>[]>(() => {
        const cells: ColumnDef<OrganizationRow, unknown>[] = [
            {
                id: 'name',
                accessorKey: 'name',
                header: 'Organization',
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <Building2 className="text-muted-foreground size-4 shrink-0" />
                        <div className="min-w-0">
                            <div className="truncate font-medium">
                                {row.original.name}
                            </div>
                            <div className="text-muted-foreground truncate text-xs">
                                {row.original.slug}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'plan',
                accessorKey: 'plan',
                header: 'Plan',
                cell: ({ row }) => row.original.plan ?? '—',
            },
            {
                // Host string, not a meaningful ordering key — deliberately
                // not sortable (enableSorting: false).
                id: 'domain',
                accessorKey: 'domain',
                header: 'Domain',
                enableSorting: false,
                cell: ({ row }) => {
                    const domain = row.original.domain;

                    if (!domain) {
                        return <span className="text-xs">—</span>;
                    }

                    // Truncate rather than wrap, so the row height stays
                    // constant; the full host is available on hover and to
                    // screen readers via the title.
                    return (
                        <span
                            title={domain}
                            className="block max-w-40 truncate text-xs sm:max-w-56"
                        >
                            {domain}
                        </span>
                    );
                },
            },
            {
                id: 'members',
                accessorKey: 'users_count',
                header: 'Members',
                meta: { align: 'right' },
                cell: ({ row }) => (
                    <span className="block text-right tabular-nums">
                        {row.original.users_count}
                    </span>
                ),
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: 'Status',
                meta: { align: 'center' },
                cell: ({ row }) => {
                    const organization = row.original;
                    const suspended = organization.status === 'suspended';
                    const tone = STATUS_TONES[organization.status] ?? 'muted';

                    if (!canEdit) {
                        return (
                            <StatusIcon
                                tone={tone}
                                label={organization.status_label}
                            />
                        );
                    }

                    // The status icon *is* the control: one affordance instead
                    // of a separate button in the actions column.
                    //
                    // router.patch(...) is used rather than an Inertia <Form>
                    // because <Form> only serialises fields bound to its own
                    // data object — with no bound input it submits an empty
                    // body and the server receives no `status` at all.
                    return (
                        <button
                            type="button"
                            title={`${suspended ? 'Reactivate' : 'Suspend'} ${organization.name}`}
                            aria-label={`${suspended ? 'Reactivate' : 'Suspend'} ${organization.name}`}
                            className="focus-visible:ring-ring rounded-md focus-visible:ring-[3px] focus-visible:outline-none"
                            onClick={(event) => {
                                event.stopPropagation();

                                setStatus(
                                    organization,
                                    suspended ? 'active' : 'suspended',
                                );
                            }}
                        >
                            <StatusIcon
                                tone={tone}
                                label={organization.status_label}
                                className="hover:opacity-70"
                            />
                        </button>
                    );
                },
            },
            {
                id: 'actions',
                header: 'Actions',
                enableSorting: false,
                cell: ({ row }) => {
                    const organization = row.original;

                    return (
                        <div
                            className="flex justify-end gap-1.5"
                            // The whole row opens the detail sheet; interactive
                            // controls inside it must not also trigger that.
                            onClick={(event) => event.stopPropagation()}
                        >
                            {canImpersonate && (
                                <Form
                                    method="post"
                                    action={orgRoutes.impersonate(
                                        organization.id,
                                    )}
                                    className="inline"
                                >
                                    <ActionIconButton
                                        type="submit"
                                        icon={<LogIn />}
                                        label={
                                            organization.domain
                                                ? `Log in as owner of ${organization.name}`
                                                : `${organization.name} has no domain configured`
                                        }
                                        color="brand"
                                        disabled={!organization.domain}
                                    />
                                </Form>
                            )}
                            {canEdit && (
                                <ActionIconButton
                                    icon={<Pencil />}
                                    label={`Edit ${organization.name}`}
                                    color="edit"
                                    onClick={() =>
                                        openRow(
                                            organization,
                                            'edit',
                                            orgRoutes.edit(
                                                organization.id,
                                            ).url,
                                        )
                                    }
                                />
                            )}
                            {canDelete && (
                                <ActionIconButton
                                    icon={<Trash2 />}
                                    label={`Delete ${organization.name}`}
                                    color="danger"
                                    onClick={() =>
                                        openRow(
                                            organization,
                                            'delete',
                                            orgRoutes.index().url,
                                        )
                                    }
                                />
                            )}
                        </div>
                    );
                },
            },
        ];

        return cells;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [canEdit, canImpersonate, canDelete, filters.search, filters.status, sort.column, sort.direction]);

    const sorting = useMemo<SortingState>(
        () =>
            sort.column
                ? [{ id: sort.column, desc: sort.direction === 'desc' }]
                : [],
        [sort.column, sort.direction],
    );

    return (
        <>
            <Head title="Organizations" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/*
                 * Title and the primary CTA are partners on one line and stay
                 * side by side all the way down to small screens. The heading
                 * component owns its own title/description spacing, so the
                 * description is rendered here rather than inside Heading to
                 * keep the two elements on one baseline.
                 */}
                <div className="flex items-center justify-between gap-3">
                    <div className="min-w-0 flex-1">
                        <Heading title="Organization management" />
                        <p className="text-muted-foreground mt-1 text-sm">
                            Create and manage organizations, their plans and
                            tenancy.
                        </p>
                    </div>
                    {canCreate && (
                        <LabelledIconButton
                            icon={<Plus />}
                            label="Create"
                            tooltip="Create organisation"
                            color="primary"
                            onClick={() =>
                                openCreateSheet(orgRoutes.create().url)
                            }
                            className="max-w-20 sm:max-w-24"
                        />
                    )}
                </div>

                {/*
                 * One card = the table's frame. Search/filters live in a header
                 * band above the rows rather than in their own floating card
                 * (that produced a box-in-box). Export sits here too because it
                 * acts on this table's result set; "New organization" is a
                 * page-level CTA and stays in the page header.
                 */}
                <Card className="gap-0 py-0">
                    <div className="border-border-strong flex flex-wrap items-center gap-2 border-b p-4">
                        <div className="relative min-w-56 flex-1">
                            <Search className="text-muted-foreground pointer-events-none absolute top-2.5 left-2.5 size-4" />
                            <Input
                                value={searchDraft}
                                onChange={(event) =>
                                    setSearchDraft(event.target.value)
                                }
                                placeholder="Search name, slug, email..."
                                aria-label="Search organizations"
                                // shadcn Input is bg-transparent, which leaves the
                                // field defined by a 1.2:1 border alone. On a card
                                // surface it needs a fill + a stronger border to be
                                // readable in both schemes.
                                className="bg-background border-border-strong pr-3 pl-9"
                            />
                        </div>
                        <ActionIconButton
                            icon={<ListFilter />}
                            label="Filters"
                            color={isFiltersOpen ? 'primary' : 'default'}
                            onClick={() =>
                                isFiltersOpen
                                    ? setOverlayParam('filters', null, {
                                          replace: true,
                                      })
                                    : setOverlayParam('filters', '1')
                            }
                        />
                        {canImport && (
                            <ActionIconButton
                                icon={<Upload />}
                                label="Import from CSV"
                                color="default"
                                onClick={() =>
                                    setOverlayParam('import', '1')
                                }
                            />
                        )}
                        {canExport && (
                            <ActionIconButton
                                icon={<Download />}
                                label="Export"
                                color="default"
                                onClick={() => setOverlayParam('export', '1')}
                            />
                        )}
                    </div>
                    <DataTable
                        columns={columns}
                        data={organizations.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        onSortingChange={applySorting}
                        loading={isTableLoading}
                        onRowClick={(organization) =>
                            setOverlayParam('mode', 'view', {
                                keep: {
                                    organization: String(organization.id),
                                },
                            })
                        }
                        empty="No organizations match your filters."
                    />
                </Card>


                {organizations.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {organizations.current_page} of{' '}
                            {organizations.last_page} ({organizations.total}{' '}
                            organizations)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={organizations.current_page <= 1}
                                onClick={() =>
                                    changePage(organizations.current_page - 1)
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    organizations.current_page >=
                                    organizations.last_page
                                }
                                onClick={() =>
                                    changePage(organizations.current_page + 1)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <ResponsiveOverlay
                open={isViewOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        closeRowSheet();
                    }
                }}
                title={
                    rowMode === 'delete'
                        ? `Delete ${viewedOrganization?.name ?? ''}?`
                        : (viewedOrganization?.name ?? 'Organization')
                }
                description={
                    !viewedOrganization
                        ? 'This organization is not on the current page of results.'
                        : rowMode === 'edit'
                          ? 'Update this organization.'
                          : rowMode === 'delete'
                            ? 'Confirm with your password to continue.'
                            : 'Details for this organization.'
                }
                footer={
                    rowMode === 'edit' && viewedOrganization ? (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={closeRowSheet}
                            >
                                Cancel
                            </Button>
                            <Button form="organization-edit-form" type="submit">
                                Save changes
                            </Button>
                        </>
                    ) : rowMode === 'delete' && viewedOrganization ? (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={closeRowSheet}
                            >
                                Cancel
                            </Button>
                            <Button
                                form="organization-delete-form"
                                type="submit"
                                variant="destructive"
                            >
                                <Trash2 className="mr-2 size-4" />
                                Delete organization
                            </Button>
                        </>
                    ) : viewedOrganization ? (
                        <>
                            {canEdit && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        openRow(
                                            viewedOrganization,
                                            'edit',
                                            orgRoutes.edit(
                                                viewedOrganization.id,
                                            ).url,
                                        )
                                    }
                                >
                                    <Pencil className="mr-2 size-4" />
                                    Edit
                                </Button>
                            )}
                            {canDelete && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        openRow(
                                            viewedOrganization,
                                            'delete',
                                            orgRoutes.index().url,
                                        )
                                    }
                                    className="text-destructive hover:text-destructive"
                                >
                                    <Trash2 className="mr-2 size-4" />
                                    Delete
                                </Button>
                            )}
                        </>
                    ) : null
                }
            >
                {viewedOrganization && rowMode === 'view' && (
                    <dl className="grid grid-cols-3 gap-x-4 gap-y-3 text-sm">
                        <dt className="text-muted-foreground">Slug</dt>
                        <dd className="col-span-2 font-medium">
                            {viewedOrganization.slug}
                        </dd>

                        <dt className="text-muted-foreground">Email</dt>
                        <dd className="col-span-2 font-medium">
                            {viewedOrganization.email ?? '—'}
                        </dd>

                        <dt className="text-muted-foreground">Phone</dt>
                        <dd className="col-span-2 font-medium">
                            {viewedOrganization.phone ?? '—'}
                        </dd>

                        <dt className="text-muted-foreground">Status</dt>
                        <dd className="col-span-2 font-medium">
                            {viewedOrganization.status_label}
                        </dd>

                        <dt className="text-muted-foreground">Plan</dt>
                        <dd className="col-span-2 font-medium">
                            {viewedOrganization.plan ?? '—'}
                        </dd>

                        <dt className="text-muted-foreground">Domain</dt>
                        <dd className="col-span-2 font-medium">
                            {viewedOrganization.domain ?? '—'}
                        </dd>

                        <dt className="text-muted-foreground">Members</dt>
                        <dd className="col-span-2 font-medium">
                            {viewedOrganization.users_count}
                        </dd>

                        <dt className="text-muted-foreground">Created</dt>
                        <dd className="col-span-2 font-medium">
                            {viewedOrganization.created_at ?? '—'}
                        </dd>
                    </dl>
                )}

                {viewedOrganization && rowMode === 'edit' && (
                    <Form
                        id="organization-edit-form"
                        method="patch"
                        action={orgRoutes.update(viewedOrganization.id)}
                        onSuccess={() => closeRowSheet()}
                        className="grid gap-3"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="edit-name">Name</Label>
                            <Input
                                id="edit-name"
                                name="name"
                                defaultValue={viewedOrganization.name}
                                required
                            />
                            {errors.name && (
                                <p className="text-destructive text-xs">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-email">Email</Label>
                            <Input
                                id="edit-email"
                                name="email"
                                type="email"
                                defaultValue={viewedOrganization.email ?? ''}
                            />
                            {errors.email && (
                                <p className="text-destructive text-xs">
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-phone">Phone</Label>
                            <Input
                                id="edit-phone"
                                name="phone"
                                defaultValue={viewedOrganization.phone ?? ''}
                            />
                            {errors.phone && (
                                <p className="text-destructive text-xs">
                                    {errors.phone}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-plan">Plan</Label>
                            <select
                                id="edit-plan"
                                name="plan_id"
                                defaultValue={viewedOrganization.plan_id ?? ''}
                                className="border-border-strong bg-background h-9 rounded-md border px-3 text-sm"
                            >
                                {plans.map((plan) => (
                                    <option
                                        key={plan.id}
                                        value={plan.id}
                                    >
                                        {plan.name}
                                    </option>
                                ))}
                            </select>
                            {errors.plan_id && (
                                <p className="text-destructive text-xs">
                                    {errors.plan_id}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-status">Status</Label>
                            <select
                                id="edit-status"
                                name="status"
                                defaultValue={viewedOrganization.status}
                                className="border-border-strong bg-background h-9 rounded-md border px-3 text-sm"
                            >
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                            </select>
                            {errors.status && (
                                <p className="text-destructive text-xs">
                                    {errors.status}
                                </p>
                            )}
                        </div>
                    </Form>
                )}

                {viewedOrganization && rowMode === 'delete' && (
                    <Form
                        id="organization-delete-form"
                        method="delete"
                        action={orgRoutes.destroy(viewedOrganization.id)}
                        onSuccess={() => closeRowSheet()}
                        className="grid gap-3"
                    >
                        <p className="text-sm">
                            This soft-deletes{' '}
                            <span className="font-medium">
                                {viewedOrganization.name}
                            </span>
                            . Its tenant, members and records are kept, so it
                            can be restored.
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
                                <p className="text-destructive text-xs">
                                    {errors.password}
                                </p>
                            )}
                        </div>
                    </Form>
                )}
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isCreateOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('create', null, { replace: true });
                    }
                }}
                title="Create organization"
                description="Set up the organization, its subdomain and its owner."
                footer={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                setOverlayParam('create', null, {
                                    replace: true,
                                })
                            }
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            form="organization-create-form"
                        >
                            Create
                        </Button>
                    </>
                }
            >
                <Form
                    id="organization-create-form"
                    method="post"
                    action={orgRoutes.store()}
                    onSuccess={() =>
                        setOverlayParam('create', null, { replace: true })
                    }
                    className="grid gap-3"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="create-name">Name</Label>
                        <Input id="create-name" name="name" required />
                        {errors.name && (
                            <p className="text-destructive text-xs">
                                {errors.name}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="create-email">Email</Label>
                        <Input
                            id="create-email"
                            name="email"
                            type="email"
                        />
                        {errors.email && (
                            <p className="text-destructive text-xs">
                                {errors.email}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="create-phone">Phone</Label>
                        <Input id="create-phone" name="phone" />
                        {errors.phone && (
                            <p className="text-destructive text-xs">
                                {errors.phone}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="create-plan">Plan</Label>
                        <select
                            id="create-plan"
                            name="plan_id"
                            defaultValue={plans[0]?.id ?? ''}
                            className="border-border-strong bg-background h-9 rounded-md border px-3 text-sm"
                        >
                            {plans.map((plan) => (
                                <option key={plan.id} value={plan.id}>
                                    {plan.name}
                                </option>
                            ))}
                        </select>
                        {errors.plan_id && (
                            <p className="text-destructive text-xs">
                                {errors.plan_id}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label>Owner</Label>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    ownerMode === 'existing'
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => setOwnerMode('existing')}
                            >
                                Existing user
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    ownerMode === 'new'
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => setOwnerMode('new')}
                            >
                                Create new person &amp; user
                            </Button>
                        </div>
                        <input
                            type="hidden"
                            name="owner_mode"
                            value={ownerMode}
                        />
                    </div>

                    {ownerMode === 'existing' ? (
                        <div className="grid gap-2">
                            <Label htmlFor="create-owner-user">
                                Owner user
                            </Label>
                            <select
                                id="create-owner-user"
                                name="owner_user_id"
                                disabled={owners === undefined}
                                defaultValue={owners?.[0]?.id ?? ''}
                                className="border-border-strong bg-background h-9 rounded-md border px-3 text-sm disabled:opacity-60"
                            >
                                {owners === undefined ? (
                                    <option value="">Loading owners…</option>
                                ) : (
                                    owners.map((owner) => (
                                        <option
                                            key={owner.id}
                                            value={owner.id}
                                        >
                                            {owner.name} ({owner.email})
                                        </option>
                                    ))
                                )}
                            </select>
                            {isOwnersLoading && (
                                <Skeleton className="h-3 w-32" />
                            )}
                            {errors.owner_user_id && (
                                <p className="text-destructive text-xs">
                                    {errors.owner_user_id}
                                </p>
                            )}
                        </div>
                    ) : (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="create-owner-first">
                                    Owner first name
                                </Label>
                                <Input
                                    id="create-owner-first"
                                    name="owner_first_name"
                                    required
                                />
                                {errors.owner_first_name && (
                                    <p className="text-destructive text-xs">
                                        {errors.owner_first_name}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="create-owner-last">
                                    Owner last name
                                </Label>
                                <Input
                                    id="create-owner-last"
                                    name="owner_last_name"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="create-owner-email">
                                    Owner email
                                </Label>
                                <Input
                                    id="create-owner-email"
                                    name="owner_email"
                                    type="email"
                                    required
                                />
                                {errors.owner_email && (
                                    <p className="text-destructive text-xs">
                                        {errors.owner_email}
                                    </p>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isExportOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('export', null, {
                            replace: true,
                        });
                    }
                }}
                title="Export organizations"
                description="Choose how many rows to include in the CSV."
                footer={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                setOverlayParam('export', null, {
                                    replace: true,
                                })
                            }
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => downloadExportUrl('page')}
                        >
                            Current page
                        </Button>
                        <Button
                            type="button"
                            onClick={() => downloadExportUrl('all')}
                        >
                            <Download className="mr-2 size-4" />
                            Export all
                        </Button>
                    </>
                }
            >
                <div className="space-y-3 text-sm">
                    <div className="border-border-strong bg-background rounded-lg border p-3">
                        <div className="font-medium">All matching</div>
                        <div className="text-muted-foreground mt-1 text-xs">
                            Every organization matching
                            {filters.search || filters.status ? (
                                <>
                                    {' '}
                                    the active search
                                    {filters.search && filters.status
                                        ? ' and '
                                        : ''}
                                    {filters.status
                                        ? 'status'
                                        : ''}{' '}
                                    filter
                                </>
                            ) : (
                                <> (no filters are active)</>
                            )}
                            . Ignores pagination — {organizations.total} row
                            {organizations.total === 1 ? '' : 's'} in total.
                        </div>
                    </div>

                    <div className="border-border-strong bg-background rounded-lg border p-3">
                        <div className="font-medium">Current page</div>
                        <div className="text-muted-foreground mt-1 text-xs">
                            Only the {organizations.data.length} row
                            {organizations.data.length === 1 ? '' : 's'} visible
                            on page {organizations.current_page}.
                        </div>
                    </div>
                </div>
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isImportOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setImportErrors([]);
                        setOverlayParam('import', null, {
                            replace: true,
                        });
                    }
                }}
                title="Import organizations"
                description="Upload a CSV to create organizations."
                footer={
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            setOverlayParam('import', null, {
                                replace: true,
                            })
                        }
                    >
                        Close
                    </Button>
                }
            >
                <Form
                    method="post"
                    action={orgRoutes.importMethod()}
                    encType="multipart/form-data"
                    onSuccess={(page) => {
                        // Flash rides on the page object itself, not `props`.
                        // A partial failure must leave the sheet open so the
                        // per-row report stays readable; only a clean run closes.
                        const reported =
                            (
                                page as unknown as {
                                    flash?: {
                                        importErrors?: {
                                            row: number;
                                            message: string;
                                        }[];
                                    };
                                }
                            ).flash?.importErrors ?? [];

                        setImportErrors(reported);

                        if (reported.length === 0) {
                            setOverlayParam('import', null, { replace: true });
                        }
                    }}
                >
                    <div className="space-y-3">
                        <div className="border-border-strong bg-background rounded-lg border p-3 text-xs">
                            <div className="font-medium">Required columns</div>
                            <div className="text-muted-foreground mt-1">
                                Name, Owner Email
                            </div>
                            <div className="font-medium mt-3">Optional</div>
                            <div className="text-muted-foreground mt-1">
                                Email, Phone, Plan (name or slug)
                            </div>
                        </div>

                        <Input
                            id="organizations-import-file"
                            name="file"
                            type="file"
                            accept=".csv,text/csv,text/plain"
                            aria-label="CSV file"
                        />

                        {errors.file && (
                            <p className="text-destructive text-xs">
                                {errors.file}
                            </p>
                        )}

                        {importErrors.length > 0 && (
                            <div
                                role="status"
                                className="border-destructive/40 bg-destructive/5 space-y-1 rounded-lg border p-3"
                            >
                                <div className="text-destructive text-xs font-medium">
                                    {importErrors.length}{' '}
                                    {importErrors.length === 1
                                        ? 'row was'
                                        : 'rows were'}{' '}
                                    skipped
                                </div>
                                <ul className="text-muted-foreground space-y-1 text-xs">
                                    {importErrors.map((problem, index) => (
                                        <li key={`${problem.row}-${index}`}>
                                            {problem.message}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <Button type="submit" className="w-full">
                            <Upload className="mr-2 size-4" />
                            Import
                        </Button>
                    </div>
                </Form>
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isFiltersOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('filters', null, {
                            replace: true,
                        });
                    }
                }}
                title="Filters"
                description="Narrow the list of organizations."
                footer={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                setOverlayParam('filters', null, {
                                    replace: true,
                                })
                            }
                        >
                            Close
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                setSearchDraft('');
                                refetchTable(
                                    {
                                        search: '',
                                        status: '',
                                        sort: sort.column ?? '',
                                        direction: sort.direction,
                                    },
                                    { replace: true },
                                );
                                setOverlayParam('filters', null, {
                                    replace: true,
                                });
                            }}
                        >
                            <X className="mr-2 size-4" />
                            Clear all
                        </Button>
                    </>
                }
            >
                <fieldset className="space-y-3">
                    <legend className="text-sm font-medium">Status</legend>
                    {[
                        { value: 'all', label: 'Any status' },
                        { value: 'active', label: 'Active' },
                        { value: 'suspended', label: 'Suspended' },
                    ].map((option) => (
                        <Label
                            key={option.value}
                            htmlFor={`status-${option.value}`}
                            className="flex cursor-pointer items-center gap-2"
                        >
                            <input
                                type="radio"
                                id={`status-${option.value}`}
                                name="status-filter"
                                className="accent-brand-primary size-4"
                                checked={
                                    (filters.status || 'all') ===
                                    option.value
                                }
                                onChange={() =>
                                    refetchTable(
                                        {
                                            search: filters.search,
                                            status:
                                                option.value === 'all'
                                                    ? ''
                                                    : option.value,
                                            sort: sort.column ?? '',
                                            direction: sort.direction,
                                            filters: '1',
                                        },
                                        { replace: true },
                                    )
                                }
                            />
                            {option.label}
                        </Label>
                    ))}
                </fieldset>
            </ResponsiveOverlay>
        </>
    );
}

OrganizationsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Organizations', href: '/organizations' },
    ],
};
