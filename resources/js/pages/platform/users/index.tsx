import { Form, Head, router, usePage } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    Download,
    ListFilter,
    Pencil,
    Plus,
    Search,
    Trash2,
    Upload,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import { OverlayFooter } from '@/components/overlay-footer';
import { PasswordConfirmDialog } from '@/components/password-confirm-dialog';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { StatusPowerToggle, StatusIcon } from '@/components/status-cell';
import type { StatusTone } from '@/components/status-cell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    RadioGroup,
    RadioGroupItem,
} from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import * as usersRoutes from '@/routes/platform/users';

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

type RowMode = 'view' | 'edit' | 'delete';

const FIELD_LABELS: Record<string, string> = {
    first_name: 'First name',
    last_name: 'Last name',
    email: 'Email',
    phone: 'Phone',
    national_id: 'National ID',
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
    const permissions = (page.props.auth?.permissions ?? []) as string[];
    const canCreate = permissions.includes('user.create');
    const canEdit = permissions.includes('user.edit');
    const canDelete = permissions.includes('user.delete');
    const canExport = permissions.includes('user.export');
    const canImport = permissions.includes('user.import');

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

    const rowMode =
        (query.get('mode') as RowMode | null) ?? 'view';
    const isCreateOpen = query.get('create') === '1';
    const isImportOpen = query.get('import') === '1';
    const isExportOpen = query.get('export') === '1';

    // `query.has()` is true even for an empty value, and closing navigates to
    // `filters=` — so comparing against '' is what actually closes the sheet.
    const isFiltersOpen =
        query.get('filters') !== null && query.get('filters') !== '';

    const [importErrors, setImportErrors] = useState<
        { row: number; message: string }[]
    >([]);
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

    // A plain <a download> would not carry the session cookie and fetch()
    // would force us to hand-save a blob, so export is a hidden form GET.
    function downloadExportUrl(scope: 'all' | 'page') {
        const url = new URL(
            usersRoutes.exportMethod().url,
            window.location.origin,
        );

        if (filters.search) {
            url.searchParams.set('search', filters.search);
        }

        if (filters.status) {
            url.searchParams.set('status', filters.status);
        }

        if (filters.role) {
            url.searchParams.set('role', filters.role);
        }

        url.searchParams.set('scope', scope);

        if (sort.column) {
            url.searchParams.set('sort', sort.column);
        }

        if (sort.direction) {
            url.searchParams.set('direction', sort.direction);
        }

        if (scope === 'page') {
            url.searchParams.set('page', String(users.current_page));
        }

        const form = document.createElement('form');
        form.method = 'GET';
        form.action = url.toString();
        form.hidden = true;

        const fields: Record<string, string> = {
            search: filters.search,
            status: filters.status,
            role: filters.role,
            scope,
            sort: sort.column ?? '',
            direction: sort.direction,
        };

        for (const [name, value] of Object.entries(fields)) {
            if (value !== '') {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            }
        }

        document.body.appendChild(form);
        form.submit();
        form.remove();

        setOverlayParam('export', null, { replace: true });
    }

    function openRow(user: UserRow, mode: RowMode) {
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
                                    <StatusPowerToggle
                                        status={user.status}
                                        activeLabel={`Suspend ${user.name}`}
                                        inactiveLabel={`Reactivate ${user.name}`}
                                        onClick={() => setSuspendTarget(user)}
                                    />
                                    <ActionIconButton
                                        icon={<Pencil />}
                                        label={`Edit ${user.name}`}
                                        color="edit"
                                        onClick={() =>
                                            openRow(user, 'edit')
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
                <div className="flex items-center justify-between gap-3">
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
                                setOverlayParam('create', '1')
                            }
                            className="max-w-20 sm:max-w-24"
                        />
                    )}
                </div>

                {/*
                 * One card = the table's frame. Search/filters live in a header
                 * band above the rows rather than in their own floating card
                 * (that produced a box-in-box). Export sits here too because it
                 * acts on this table's result set; "New user" is a page-level
                 * CTA and stays in the page header.
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
                                placeholder="Search name, email, phone..."
                                aria-label="Search users"
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
                        {canImport && (
                            <ActionIconButton
                                icon={<Upload />}
                                label="Import from CSV"
                                color="teal"
                                onClick={() => setOverlayParam('import', '1')}
                            />
                        )}
                        {canExport && (
                            <ActionIconButton
                                icon={<Download />}
                                label="Export"
                                color="teal"
                                onClick={() => setOverlayParam('export', '1')}
                            />
                        )}
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
                          : rowMode === 'edit'
                            ? 'Update the person, the account and their roles.'
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
                    ) : rowMode === 'edit' && viewedUser ? (
                        <OverlayFooter
                            onCancel={closeRowSheet}
                            actions={[
                                {
                                    label: 'Save changes',
                                    form: 'user-edit-form',
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
                                    onClick: () => openRow(viewedUser!, 'edit'),
                                    ...(canEdit ? {} : { disabled: true }),
                                },
                            ]}
                        />
                    )
                }
            >
                {viewedUser &&
                    (rowMode === 'edit' ? (
                        <Form
                            id="user-edit-form"
                            method="patch"
                            action={usersRoutes.update(viewedUser.id)}
                            onSuccess={() => closeRowSheet()}
                            className="grid gap-3 py-2"
                        >
                            {(['first_name', 'last_name', 'email', 'phone', 'national_id'] as const).map(
                                (field) => (
                                    <div key={field} className="grid gap-2">
                                        <Label htmlFor={`edit-${field}`}>
                                            {FIELD_LABELS[field]}
                                        </Label>
                                        <Input
                                            id={`edit-${field}`}
                                            name={field}
                                            defaultValue={
                                                (viewedUser.person?.[
                                                    field as keyof PersonRow
                                                ] as string | null) ??
                                                (field === 'email'
                                                    ? viewedUser.email
                                                    : null) ??
                                                ''
                                            }
                                            required={field === 'first_name' || field === 'email'}
                                        />
                                        {errors[field] && (
                                            <p className="text-xs text-destructive">
                                                {errors[field]}
                                            </p>
                                        )}
                                    </div>
                                ),
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="edit-status">Status</Label>
                                <Select
                                    name="status"
                                    defaultValue={viewedUser.status}
                                >
                                    <SelectTrigger id="edit-status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">
                                            Active
                                        </SelectItem>
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
                                <Label>Platform roles</Label>
                                <RadioGroup
                                    name="roles"
                                    defaultValue={viewedUser.roles
                                        .map((r) => r.name)
                                        .join(',')}
                                    className="grid gap-2"
                                >
                                    {platformRoles.map((role) => (
                                        <div
                                            key={role.value}
                                            className="flex items-center gap-2"
                                        >
                                            <RadioGroupItem
                                                id={`edit-role-${role.value}`}
                                                value={role.value}
                                            />
                                            <Label
                                                htmlFor={`edit-role-${role.value}`}
                                                className="cursor-pointer"
                                            >
                                                {role.label}
                                            </Label>
                                        </div>
                                    ))}
                                </RadioGroup>
                                <input
                                    type="hidden"
                                    name="roles[]"
                                    value={viewedUser.roles.map((r) => r.name).join(',')}
                                />
                                {errors.roles && (
                                    <p className="text-xs text-destructive">
                                        {errors.roles}
                                    </p>
                                )}
                            </div>
                        </Form>
                    ) : (
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
                                              viewedUser.person
                                                  .first_name,
                                              viewedUser.person.last_name,
                                          ]
                                              .filter(Boolean)
                                              .join(' ') || '—'
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
                    ))}
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
                        <Label>Status</Label>
                        {/* Same reasoning as the role filter: three options
                            plus "any" is a radio group, not a dropdown. */}
                        <RadioGroup
                            value={filters.status || 'any'}
                            onValueChange={(value) =>
                                refetchTable(
                                    {
                                        search: filters.search,
                                        status: value === 'any' ? '' : value,
                                        role: filters.role,
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
                                ['suspended', 'Suspended'],
                            ].map(([value, text]) => (
                                <div key={value} className="flex items-center gap-2">
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
                        <Label>Platform roles</Label>
                        {/* Radix Select reserves the empty value for the
                            placeholder, so "Any role" is not expressible as an
                            item. A RadioGroup has no such reservation. */}
                        <RadioGroup
                            value={filters.role || 'all'}
                            onValueChange={(value) =>
                                refetchTable(
                                    {
                                        search: filters.search,
                                        status: filters.status,
                                        role: value === 'all' ? '' : value,
                                        sort: sort.column ?? '',
                                        direction: sort.direction,
                                        filters: '1',
                                    },
                                    { replace: true },
                                )
                            }
                            className="grid gap-2"
                        >
                            <div className="flex items-center gap-2">
                                <RadioGroupItem
                                    id="filter-role-all"
                                    value="all"
                                />
                                <Label
                                    htmlFor="filter-role-all"
                                    className="cursor-pointer"
                                >
                                    Any role
                                </Label>
                            </div>
                            {platformRoles.map((role) => (
                                <div
                                    key={role.value}
                                    className="flex items-center gap-2"
                                >
                                    <RadioGroupItem
                                        id={`filter-role-${role.value}`}
                                        value={role.value}
                                    />
                                    <Label
                                        htmlFor={`filter-role-${role.value}`}
                                        className="cursor-pointer"
                                    >
                                        {role.label}
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

            <ResponsiveOverlay
                open={isExportOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('export', null, { replace: true });
                    }
                }}
                title="Export users"
                description="Choose how many rows to include in the CSV."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('export', null, { replace: true })
                        }
                        actions={[
                            {
                                label: 'Current page',
                                tone: 'secondary',
                                onClick: () => downloadExportUrl('page'),
                            },
                            {
                                label: 'Export all',
                                icon: <Download className="size-4" />,
                                onClick: () => downloadExportUrl('all'),
                            },
                        ]}
                    />
                }
            >
                <div className="space-y-3 text-sm">
                    <div className="rounded-lg border border-border-strong bg-background p-3">
                        <div className="font-medium">All matching</div>
                        <div className="mt-1 text-xs text-muted-foreground">
                            Every user matching
                            {filters.search || filters.status || filters.role ? (
                                <>
                                    {' '}
                                    the active search
                                    {filters.status ? ' and status' : ''}
                                    {filters.role ? ' and role' : ''} filter
                                </>
                            ) : (
                                <> (no filters are active)</>
                            )}
                            . Ignores pagination — {users.total} row
                            {users.total === 1 ? '' : 's'} in total.
                        </div>
                    </div>

                    <div className="rounded-lg border border-border-strong bg-background p-3">
                        <div className="font-medium">Current page</div>
                        <div className="mt-1 text-xs text-muted-foreground">
                            Only the {users.data.length} row
                            {users.data.length === 1 ? '' : 's'} visible on page{' '}
                            {users.current_page}.
                        </div>
                    </div>
                </div>
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isCreateOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('create', null, { replace: true });
                    }
                }}
                title="Create user"
                description="Set up the person, their account and their platform roles."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('create', null, { replace: true })
                        }
                        actions={[
                            {
                                label: 'Create user',
                                form: 'user-create-form',
                                type: 'submit',
                            },
                        ]}
                    />
                }
            >
                <Form
                    id="user-create-form"
                    method="post"
                    action={usersRoutes.store()}
                    onSuccess={() =>
                        setOverlayParam('create', null, { replace: true })
                    }
                    className="grid gap-3 py-2"
                >
                    {(['first_name', 'last_name', 'email', 'phone', 'national_id'] as const).map(
                        (field) => (
                            <div key={field} className="grid gap-2">
                                <Label htmlFor={`create-${field}`}>
                                    {FIELD_LABELS[field]}
                                </Label>
                                <Input
                                    id={`create-${field}`}
                                    name={field}
                                    required={field === 'first_name' || field === 'email'}
                                />
                                {errors[field] && (
                                    <p className="text-xs text-destructive">
                                        {errors[field]}
                                    </p>
                                )}
                            </div>
                        ),
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="create-status">Status</Label>
                        <Select name="status" defaultValue="active">
                            <SelectTrigger id="create-status">
                                <SelectValue />
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
                        <Label>Platform roles</Label>
                        <RadioGroup name="roles" defaultValue="" className="grid gap-2">
                            {platformRoles.map((role) => (
                                <div
                                    key={role.value}
                                    className="flex items-center gap-2"
                                >
                                    <RadioGroupItem
                                        id={`create-role-${role.value}`}
                                        value={role.value}
                                    />
                                    <Label
                                        htmlFor={`create-role-${role.value}`}
                                        className="cursor-pointer"
                                    >
                                        {role.label}
                                    </Label>
                                </div>
                            ))}
                        </RadioGroup>
                        <input type="hidden" name="roles[]" value="" readOnly />
                        {errors.roles && (
                            <p className="text-xs text-destructive">
                                {errors.roles}
                            </p>
                        )}
                    </div>
                </Form>
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isImportOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setImportErrors([]);
                        setOverlayParam('import', null, { replace: true });
                    }
                }}
                title="Import users"
                description="Upload a CSV to create users."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('import', null, { replace: true })
                        }
                        cancelLabel="Close"
                    />
                }
            >
                <Form
                    method="post"
                    action={usersRoutes.importMethod()}
                    encType="multipart/form-data"
                    onSuccess={(page: unknown) => {
                        // Flash rides on the page object itself, not `props`.
                        // A partial failure must leave the sheet open so the
                        // per-row report stays readable.
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
                    className="space-y-3 py-2"
                >
                    <div className="border-border-strong bg-background space-y-1 rounded-lg border p-3 text-xs">
                        <div className="font-medium">Required columns</div>
                        <div className="text-muted-foreground mt-1">
                            First Name, Email
                        </div>
                        <div className="font-medium mt-3">Optional</div>
                        <div className="text-muted-foreground">
                            Last Name, Phone, National ID, Gender, Status, Roles
                            (pipe-separated)
                        </div>
                    </div>

                    <Input
                        id="users-import-file"
                        name="file"
                        type="file"
                        accept=".csv,text/csv,text/plain"
                        aria-label="CSV file"
                    />

                    {errors.file && (
                        <p className="text-xs text-destructive">
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
                </Form>
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