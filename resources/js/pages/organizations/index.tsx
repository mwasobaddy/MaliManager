import { Head, Link, router } from '@inertiajs/react';
import { Form, usePage } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    Building2,
    Download,
    Eye,
    ListFilter,
    LogIn,
    Pencil,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { PasswordConfirmDialog } from '@/components/password-confirm-dialog';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { StatusIcon  } from '@/components/status-cell';
import type {StatusTone} from '@/components/status-cell';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
}: Props) {
    const page = usePage();
    const permissions =
        ((page.props.auth?.permissions ?? []) as string[]) ?? [];
    const canCreate = permissions.includes('organization.create');
    const canEdit = permissions.includes('organization.edit');
    const canDelete = permissions.includes('organization.delete');
    const canExport = permissions.includes('organization.export');
    const canImpersonate = permissions.includes('organization.impersonate');

    const [deleteTarget, setDeleteTarget] = useState<OrganizationRow | null>(
        null,
    );

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

    const isFiltersOpen = query.has('filters');

    function navigate(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        router.get(
            orgRoutes.index().url,
            params,
            { preserveState: true, replace: options?.replace ?? false },
        );
    }

    // Overlays push history so Back closes them; closing replaces so Back does
    // not walk back through every sheet the user opened.
    function setOverlayParam(
        key: string,
        value: string | null,
        options?: { replace?: boolean },
    ) {
        navigate(
            {
                search: filters.search,
                status: filters.status,
                sort: sort.column ?? '',
                direction: sort.direction,
                [key]: value ?? '',
            },
            options,
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
                },
            },
        );
    }

    function changePage(nextPage: number) {
        navigate({
            search: filters.search,
            status: filters.status,
            sort: sort.column ?? '',
            direction: sort.direction,
            page: nextPage,
        });
    }

    function applySorting(next: SortingState) {
        const first = next[0];
        navigate(
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
            navigate(
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
                cell: ({ row }) => (
                    <span className="text-xs">
                        {row.original.domain ?? '—'}
                    </span>
                ),
            },
            {
                id: 'members',
                accessorKey: 'users_count',
                header: 'Members',
                cell: ({ row }) => row.original.users_count,
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: 'Status',
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
                            onClick={() =>
                                setStatus(
                                    organization,
                                    suspended ? 'active' : 'suspended',
                                )
                            }
                        >
                            <StatusIcon
                                tone={tone}
                                label={organization.status_label}
                                className="hover:brightness-95"
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
                        <div className="flex justify-end gap-1.5">
                            <ActionIconButton
                                icon={<Eye />}
                                label={`View ${organization.name}`}
                                color="view"
                                onClick={() =>
                                    setOverlayParam(
                                        'organization',
                                        String(organization.id),
                                    )
                                }
                            />
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
                                    asChild
                                    label={`Edit ${organization.name}`}
                                    color="edit"
                                >
                                    <Link
                                        href={orgRoutes.edit(
                                            organization.id,
                                        )}
                                    >
                                        <Pencil />
                                    </Link>
                                </ActionIconButton>
                            )}
                            {canDelete && (
                                <ActionIconButton
                                    icon={<Trash2 />}
                                    label={`Delete ${organization.name}`}
                                    color="danger"
                                    onClick={() =>
                                        setDeleteTarget(organization)
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
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Organization management"
                        description="Create and manage organizations, their plans and tenancy."
                    />
                    <div className="flex gap-2">
                        {canCreate && (
                            <Button asChild>
                                <Link href={orgRoutes.create()}>
                                    <Plus className="mr-2 size-4" />
                                    New organization
                                </Link>
                            </Button>
                        )}
                    </div>
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
                        {canExport && (
                            <Button variant="outline" size="sm" asChild>
                                <a href={orgRoutes.exportMethod().url}>
                                    <Download className="mr-2 size-4" />
                                    Export
                                </a>
                            </Button>
                        )}
                    </div>
                    <DataTable
                        columns={columns}
                        data={organizations.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        onSortingChange={applySorting}
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

            <PasswordConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
                formAction={orgRoutes.destroy.form(deleteTarget?.id ?? 0)}
                title={`Delete ${deleteTarget?.name ?? ''}?`}
                description="This soft-deletes the organization. Its tenant, members and records are kept, so it can be restored. Please enter your password to confirm."
            />

            <ResponsiveOverlay
                open={isViewOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('organization', null, {
                            replace: true,
                        });
                    }
                }}
                title={viewedOrganization?.name ?? 'Organization'}
                description={
                    viewedOrganization
                        ? 'Details for this organization.'
                        : 'This organization is not on the current page of results.'
                }
            >
                {viewedOrganization && (
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
                                navigate(
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
                                    navigate(
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
