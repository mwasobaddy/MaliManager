import { Head, router, usePage } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import { Download, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import { OverlayFooter } from '@/components/overlay-footer';
import { PasswordConfirmDialog } from '@/components/password-confirm-dialog';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { StatusIcon, StatusToggleButton } from '@/components/status-cell';
import type { StatusTone } from '@/components/status-cell';
import { Badge } from '@/components/ui/badge';
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
import * as usersRoutes from '@/routes/users';

type PersonRow = {
    id: number;
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    national_id: string | null;
    gender: string | null;
    status: string;
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    status: string;
    roles: { id: number; name: string }[];
    person?: PersonRow | null;
    created_at?: string | null;
};

type Props = {
    users: {
        data: UserRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    /** Row the URL points at when it is not on the current page. */
    focusedUser?: UserRow | null;
    filters: { search: string; status: string; role: string };
    sort: { column: string | null; direction: 'asc' | 'desc' };
    platformRoles: { value: string; label: string }[];
};

const STATUS_TONES: Record<string, StatusTone> = {
    active: 'success',
    inactive: 'muted',
    suspended: 'danger',
};

/** Debounce so a fast typist issues one request, not one per keystroke. */
const SEARCH_DEBOUNCE_MS = 350;

export default function UsersIndex({
    users,
    focusedUser,
    filters,
    sort,
    platformRoles,
}: Props) {
    const page = usePage();
    const permissions =
        ((page.props.auth?.permissions ?? []) as string[]) ?? [];
    const canCreate = permissions.includes('user.create');
    const canEdit = permissions.includes('user.edit');
    const canDelete = permissions.includes('user.delete');
    const canExport = permissions.includes('user.export');

    const [isTableLoading, setIsTableLoading] = useState(false);
    const [sorting, setSorting] = useState<SortingState>(
        sort.column
            ? [{ id: sort.column, desc: sort.direction === 'desc' }]
            : [],
    );

    // Read the overlay state from the URL rather than component state, so the
    // sheet survives Back/Forward and can be deep-linked.
    const query = useMemo(() => {
        const index = page.url.indexOf('?');

        return new URLSearchParams(index === -1 ? '' : page.url.slice(index));
    }, [page.url]);


    const viewedId = Number(query.get('user'));
    const isViewOpen = Number.isFinite(viewedId) && viewedId > 0;
    const viewedUser = isViewOpen
        ? (users.data.find((user) => user.id === viewedId) ?? focusedUser)
        : undefined;

    const rowMode = (query.get('mode') as 'view' | 'delete' | null) ?? 'view';
    const [deleteTarget, setDeleteTarget] = useState<UserRow | null>(null);
    const [suspending, setSuspending] = useState(false);
    const [suspendTarget, setSuspendTarget] = useState<UserRow | null>(null);

    const errors = (page.props.errors ?? {}) as Record<string, string>;

    /**
     * Changes only the URL, never the result set — opening or closing a sheet.
     * No prop is requested because nothing on the page changes with the URL.
     */
    function navigate(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        router.get(usersRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only: ['users', 'focusedUser', 'filters', 'sort'],
        });
    }

    /**
     * Replaces the visible rows, so the table shows a skeleton while in flight.
     * `only` keeps this off props the table does not read.
     */
    function refetchTable(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        setIsTableLoading(true);

        router.get(usersRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only: ['users', 'focusedUser', 'filters', 'sort'],
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
                status: filters.status,
                role: filters.role,
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
                status: filters.status,
                role: filters.role,
                sort: sort.column ?? '',
                direction: sort.direction,
            },
            { replace: true },
        );
    }

    function openRow(user: UserRow, mode: 'view' | 'delete') {
        setOverlayParam('mode', mode, { keep: { user: String(user.id) } });
    }

    function changePage(nextPage: number) {
        refetchTable({
            search: filters.search,
            status: filters.status,
            role: filters.role,
            sort: sort.column ?? '',
            direction: sort.direction,
            page: nextPage,
        });
    }

    function applySorting(next: SortingState) {
        setSorting(next);
        const first = next[0];
        refetchTable(
            {
                search: filters.search,
                status: filters.status,
                role: filters.role,
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
                    role: filters.role,
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
    const [serverSearch, setServerSearch] = useState(filters.search);

    if (filters.search !== serverSearch) {
        setServerSearch(filters.search);
        setSearchDraft(filters.search);
    }

    function confirmToggleStatus() {
        if (!suspendTarget) {
            return;
        }

        setSuspending(true);
        router.patch(
            usersRoutes.status(suspendTarget.id),
            {
                status:
                    suspendTarget.status === 'active' ? 'suspended' : 'active',
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSuspending(false);
                    setSuspendTarget(null);
                },
            },
        );
    }

    const columns = useMemo<ColumnDef<UserRow, unknown>[]>(() => {
        const cells: ColumnDef<UserRow, unknown>[] = [
            {
                id: 'name',
                accessorKey: 'name',
                header: 'User',
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <div className="min-w-0">
                            <div className="truncate font-medium">
                                {row.original.name}
                            </div>
                            {row.original.person && (
                                <div className="text-muted-foreground truncate text-xs">
                                    {[row.original.person.first_name, row.original.person.last_name]
                                        .filter(Boolean)
                                        .join(' ') || '—'}
                                </div>
                            )}
                        </div>
                    </div>
                ),
            },
            {
                id: 'email',
                accessorKey: 'email',
                header: 'Email',
                cell: ({ row }) => (
                    <span className="block truncate">
                        {row.original.email}
                    </span>
                ),
            },
            {
                id: 'roles',
                header: 'Roles',
                enableSorting: false,
                cell: ({ row }) => (
                    <div className="flex flex-wrap gap-1">
                        {row.original.roles.length === 0 && (
                            <span className="text-muted-foreground">—</span>
                        )}
                        {row.original.roles.map((role) => (
                            <Badge key={role.id} variant="secondary">
                                {role.name}
                            </Badge>
                        ))}
                    </div>
                ),
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: 'Status',
                meta: { align: 'center' },
                cell: ({ row }) => {
                    const status = row.original.status;

                    return (
                        <StatusIcon
                            tone={STATUS_TONES[status] ?? 'muted'}
                            label={status}
                        />
                    );
                },
            },
            {
                id: 'actions',
                header: 'Actions',
                enableSorting: false,
                meta: { align: 'right' },
                cell: ({ row }) => {
                    const user = row.original;

                    return (
                        <div
                            className="flex justify-end gap-1"
                            onClick={(event) => event.stopPropagation()}
                        >
                            {canEdit && (
                                <>
                                    <StatusToggleButton
                                        tone={
                                            STATUS_TONES[user.status] ?? 'muted'
                                        }
                                        label={user.status}
                                        actionLabel={`${
                                            user.status === 'active'
                                                ? 'Suspend'
                                                : 'Activate'
                                        } ${user.name}`}
                                        onClick={() => setSuspendTarget(user)}
                                    />
                                    <ActionIconButton
                                        icon={<Pencil />}
                                        label={`Edit ${user.name}`}
                                        color="edit"
                                        onClick={() =>
                                            router.visit(
                                                usersRoutes.edit(user.id).url,
                                            )
                                        }
                                    />
                                </>
                            )}
                            {canDelete && (
                                <ActionIconButton
                                    icon={<Trash2 />}
                                    label={`Delete ${user.name}`}
                                    color="danger"
                                    onClick={() => setDeleteTarget(user)}
                                />
                            )}
                        </div>
                    );
                },
            },
        ];

        return cells;
    }, [canEdit, canDelete]);

    return (
        <>
            <Head title="Users" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="User management"
                        description="Create and manage people and their user accounts."
                    />
                    {canCreate && (
                        <LabelledIconButton
                            icon={<Plus />}
                            label="New user"
                            tooltip="Create user"
                            color="primary"
                            onClick={() =>
                                router.visit(usersRoutes.create().url)
                            }
                            className="max-w-20 sm:max-w-24"
                        />
                    )}
                </div>

                <Card>
                    <div className="flex items-center justify-between gap-3 border-b p-4">
                        <div className="relative min-w-56 flex-1">
                            <Search className="absolute top-2.5 left-2 size-4 text-muted-foreground" />
                            <Input
                                value={searchDraft}
                                onChange={(event) =>
                                    setSearchDraft(event.target.value)
                                }
                                placeholder="Search name, email, phone..."
                                className="pr-3 pl-8"
                                aria-label="Search users"
                            />
                        </div>
                        <div className="flex gap-1">
                            <ActionIconButton
                                icon={<Plus />}
                                label="Filters"
                                color="default"
                                onClick={() =>
                                    setOverlayParam('filters', '1')
                                }
                                className="sm:hidden"
                            />
                            {canExport && (
                                <ActionIconButton
                                    icon={<Download />}
                                    label="Export"
                                    color="default"
                                    onClick={() =>
                                        router.visit(
                                            usersRoutes.exportMethod().url,
                                        )
                                    }
                                />
                            )}
                        </div>
                    </div>

                    <DataTable
                        columns={columns}
                        data={users.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        onSortingChange={applySorting}
                        loading={isTableLoading}
                        onRowClick={(user) => openRow(user, 'view')}
                        empty="No users match your filters."
                    />
                </Card>

                {users.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {users.current_page} of {users.last_page} (
                            {users.total} users)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={users.current_page <= 1}
                                onClick={() =>
                                    changePage(users.current_page - 1)
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={users.current_page >= users.last_page}
                                onClick={() =>
                                    changePage(users.current_page + 1)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <ResponsiveOverlay
                open={isViewOpen && viewedUser !== undefined}
                onOpenChange={(open) => {
                    if (!open) {
                        closeRowSheet();
                    }
                }}
                title={viewedUser?.name ?? 'User'}
                description={
                    !viewedUser
                        ? 'This user is not on the current page of results.'
                        : rowMode === 'delete'
                          ? 'This soft-deletes the account. Its records are kept.'
                          : 'Details for this user.'
                }
                footer={
                    rowMode === 'delete' && viewedUser ? (
                        <OverlayFooter
                            onCancel={closeRowSheet}
                            actions={[
                                {
                                    label: 'Delete account',
                                    tone: 'danger',
                                    icon: <Trash2 className="size-4" />,
                                    onClick: () => {
                                        setDeleteTarget(viewedUser);
                                        closeRowSheet();
                                    },
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
                                        router.visit(
                                            usersRoutes.edit(
                                                viewedUser?.id ?? 0,
                                            ).url,
                                        ),
                                    ...(canEdit ? {} : { disabled: true }),
                                },
                            ]}
                        />
                    )
                }
            >
                {viewedUser && (
                    <dl className="grid gap-3 py-2 text-sm">
                        {[
                            ['Email', viewedUser.email],
                            ['Phone', viewedUser.phone ?? '—'],
                            ['Status', viewedUser.status],
                            [
                                'Roles',
                                viewedUser.roles.length > 0
                                    ? viewedUser.roles
                                          .map((role) => role.name)
                                          .join(', ')
                                    : '—',
                            ],
                            [
                                'Person',
                                viewedUser.person
                                    ? [
                                          viewedUser.person.first_name,
                                          viewedUser.person.last_name,
                                      ]
                                          .filter(Boolean)
                                          .join(' ') ||
                                      '—'
                                    : '—',
                            ],
                            [
                                'National ID',
                                viewedUser.person?.national_id ?? '—',
                            ],
                            ['Created', viewedUser.created_at ?? '—'],
                        ].map(([label, value]) => (
                            <div
                                key={label}
                                className="grid grid-cols-[7rem_1fr] gap-2"
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
                )}
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={query.get('filters') !== null && query.get('filters') !== ''}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('filters', null, { replace: true });
                    }
                }}
                title="Filters"
                description="Narrow the list of users."
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
                                    refetchTable({
                                        search: '',
                                        status: '',
                                        role: '',
                                        sort: sort.column ?? '',
                                        direction: sort.direction,
                                    });
                                    setOverlayParam('filters', null, {
                                        replace: true,
                                    });
                                },
                            },
                        ]}
                    />
                }
            >
                <div className="grid gap-4 py-2">
                    <div className="grid gap-2">
                        <Label htmlFor="filter-status">Status</Label>
                        <Select
                            value={filters.status}
                            onValueChange={(value) =>
                                refetchTable(
                                    {
                                        search: filters.search,
                                        status: value,
                                        role: filters.role,
                                        sort: sort.column ?? '',
                                        direction: sort.direction,
                                        filters: '1',
                                    },
                                    { replace: true },
                                )
                            }
                        >
                            <SelectTrigger id="filter-status">
                                <SelectValue placeholder="Any status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">
                                    Inactive
                                </SelectItem>
                                <SelectItem value="suspended">
                                    Suspended
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="filter-role">Role</Label>
                        <Select
                            value={filters.role}
                            onValueChange={(value) =>
                                refetchTable(
                                    {
                                        search: filters.search,
                                        status: filters.status,
                                        role: value,
                                        sort: sort.column ?? '',
                                        direction: sort.direction,
                                        filters: '1',
                                    },
                                    { replace: true },
                                )
                            }
                        >
                            <SelectTrigger id="filter-role">
                                <SelectValue placeholder="Any role" />
                            </SelectTrigger>
                            <SelectContent>
                                {platformRoles.map((role) => (
                                    <SelectItem
                                        key={role.value}
                                        value={role.value}
                                    >
                                        {role.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="filter-search">Search</Label>
                        <Input
                            id="filter-search"
                            value={searchDraft}
                            onChange={(event) =>
                                setSearchDraft(event.target.value)
                            }
                            placeholder="Name, email, phone..."
                        />
                        {errors.search && (
                            <p className="text-xs text-destructive">
                                {errors.search}
                            </p>
                        )}
                    </div>
                </div>
            </ResponsiveOverlay>

            <PasswordConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
                formAction={usersRoutes.destroy.form(deleteTarget?.id ?? 0)}
                title={`Delete ${deleteTarget?.email ?? ''}?`}
                description="This soft-deletes the user account. Please enter your password to confirm."
            />

            <ResponsiveOverlay
                open={suspendTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSuspendTarget(null);
                    }
                }}
                title={
                    suspendTarget?.status === 'active'
                        ? 'Suspend user?'
                        : 'Activate user?'
                }
                description={
                    suspendTarget?.status === 'active'
                        ? `Suspend ${suspendTarget?.email ?? ''}? They will be signed out of every device and unable to access the platform until reactivated.`
                        : `Reactivate ${suspendTarget?.email ?? ''}? They will be able to sign in again.`
                }
                footer={
                    <OverlayFooter
                        onCancel={() => setSuspendTarget(null)}
                        actions={[
                            {
                                label:
                                    suspendTarget?.status === 'active'
                                        ? 'Suspend'
                                        : 'Activate',
                                // Suspending locks people out, so it takes the
                                // danger colour; reactivating restores access
                                // and is an ordinary confirmation.
                                tone:
                                    suspendTarget?.status === 'active'
                                        ? 'danger'
                                        : 'primary',
                                disabled: suspending,
                                onClick: confirmToggleStatus,
                            },
                        ]}
                    />
                }
            >
                <div className="py-2" />
            </ResponsiveOverlay>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Users', href: '/users' },
    ],
};