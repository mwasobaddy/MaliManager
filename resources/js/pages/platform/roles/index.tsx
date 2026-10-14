import { Head, router } from '@inertiajs/react';
import { Form, usePage } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    Download,
    ListFilter,
    Pencil,
    Plus,
    Power,
    Search,
    Shield,
    Trash2,
    Upload,
    UsersRound,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { CsvImportSheet, downloadCsv } from '@/components/csv-actions';
import { CsvExportSheet } from '@/components/csv-export-sheet';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { StatusIcon } from '@/components/status-cell';
import type { StatusTone } from '@/components/status-cell';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import * as roleRoutes from '@/routes/platform/roles';
import { index as usersIndex } from '@/routes/platform/users';

type PermissionRef = { value: string; label: string };

type RoleRow = {
    id: number;
    name: string;
    label: string;
    is_active: boolean;
    /** True for the four built-ins, which cannot be created away or deleted. */
    is_protected: boolean;
    users_count: number;
    permissions_count: number;
    permissions: PermissionRef[];
};

type Props = {
    roles: {
        data: RoleRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    permissionCatalog: PermissionRef[];
    canCreate: boolean;
    canEdit: boolean;
    canDelete: boolean;
    canImport: boolean;
    canExport: boolean;
};

const STATUS_TONES: Record<string, StatusTone> = {
    active: 'success',
    inactive: 'warning',
};

/** Debounce so a fast typist issues one request, not one per keystroke. */
const SEARCH_DEBOUNCE_MS = 350;

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

export default function RolesIndex({
    roles,
    permissionCatalog,
    canCreate,
    canEdit,
    canDelete,
    canImport,
    canExport,
}: Props) {
    const page = usePage();
    const query = useQuery();
    const errors = (page.props.errors ?? {}) as Record<string, string>;

    const filters = {
        search: query.get('search') ?? '',
        status: query.get('status') ?? '',
        type: query.get('type') ?? '',
    };

    const sort = {
        column: query.get('sort') || null,
        direction: query.get('direction') === 'asc' ? 'asc' : 'desc',
    };

    // One sheet per row action rather than a sheet per action: stacking Radix
    // modals fights over the focus trap and double-dims the page.
    type RowMode = 'view' | 'edit' | 'deactivate' | 'delete';
    const rowMode = (query.get('mode') as RowMode | null) ?? 'view';
    const isViewOpen = query.get('role') !== null;
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
            filters.status ? 'the status filter' : '',
            filters.type ? 'the type filter' : '',
        ]
            .filter(Boolean)
            .join(' and ') || null;

    /**
     * Changes only the URL, never the result set — opening or closing a sheet.
     */
    function navigate(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        router.get(roleRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only: [
                'roles',
                'permissionCatalog',
                'canCreate',
                'canEdit',
                'canDelete',
                'canImport',
                'canExport',
            ],
        });
    }

    /** Replaces the visible rows, so the table shows a skeleton in flight. */
    function refetchTable(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        setIsTableLoading(true);

        router.get(roleRoutes.index().url, params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only: [
                'roles',
                'permissionCatalog',
                'canCreate',
                'canEdit',
                'canDelete',
                'canImport',
                'canExport',
            ],
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
            keep?: Record<string, string>;
        },
    ) {
        navigate(
            {
                search: filters.search,
                status: filters.status,
                type: filters.type,
                sort: sort.column ?? '',
                direction: sort.direction,
                ...(options?.keep ?? {}),
                [key]: value ?? '',
            },
            { replace: options?.replace },
        );
    }

    /** Close the row sheet, dropping both the role id and the mode. */
    function closeRowSheet() {
        navigate(
            {
                search: filters.search,
                status: filters.status,
                type: filters.type,
                sort: sort.column ?? '',
                direction: sort.direction,
            },
            { replace: true },
        );
    }

    function openRow(role: RoleRow, mode: RowMode) {
        setOverlayParam('mode', mode, { keep: { role: String(role.id) } });
    }

    function applySorting(next: SortingState) {
        const first = next[0];

        refetchTable(
            {
                search: filters.search,
                status: filters.status,
                type: filters.type,
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
                type: filters.type,
                sort: sort.column ?? '',
                direction: sort.direction,
                page: nextPage,
            },
            { replace: true },
        );
    }

    /**
     * The filters the export has to carry, so "Export all" means "everything
     * the table is showing" rather than everything.
     */
    function exportParams(scope: 'all' | 'page') {
        return {
            search: filters.search,
            status: filters.status,
            type: filters.type,
            scope,
            ...(scope === 'page' ? { page: roles.current_page } : {}),
        };
    }

    function runExport(scope: 'all' | 'page') {
        downloadCsv(roleRoutes.exportMethod().url, exportParams(scope));
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
                    status: filters.status,
                    type: filters.type,
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

    const sorting = useMemo<SortingState>(
        () =>
            sort.column
                ? [{ id: sort.column, desc: sort.direction === 'desc' }]
                : [],
        [sort.column, sort.direction],
    );

    const columns = useMemo<ColumnDef<RoleRow, unknown>[]>(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: 'Role',
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <Shield className="size-4 shrink-0 text-muted-foreground" />
                        <span className="flex items-center gap-2">
                            <span className="font-medium">
                                {row.original.label}
                            </span>
                            {row.original.is_protected && (
                                <span className="text-muted-foreground rounded border px-1.5 py-0.5 text-[10px] uppercase tracking-wide">
                                    Built-in
                                </span>
                            )}
                            {!row.original.is_active && (
                                <span className="text-muted-foreground rounded border px-1.5 py-0.5 text-[10px] uppercase tracking-wide">
                                    Inactive
                                </span>
                            )}
                        </span>
                    </div>
                ),
            },
            {
                id: 'permissions',
                accessorKey: 'permissions_count',
                header: 'Central permissions',
                cell: ({ row }) =>
                    row.original.permissions_count === 0 ? (
                        <span className="text-xs text-muted-foreground">
                            None
                        </span>
                    ) : (
                        <span
                            title={row.original.permissions
                                .map((p) => p.label)
                                .join('\n')}
                            className="block text-xs tabular-nums"
                        >
                            {row.original.permissions_count}{' '}
                            {row.original.permissions_count === 1
                                ? 'permission'
                                : 'permissions'}
                        </span>
                    ),
            },
            {
                id: 'users',
                accessorKey: 'users_count',
                header: 'Users',
                cell: ({ row }) => (
                    <span className="text-xs tabular-nums">
                        {row.original.users_count}
                    </span>
                ),
            },
            {
                id: 'status',
                accessorKey: 'is_active',
                header: 'Status',
                cell: ({ row }) => (
                    <StatusIcon
                        tone={
                            row.original.is_active
                                ? STATUS_TONES.active
                                : STATUS_TONES.inactive
                        }
                        label={row.original.is_active ? 'Active' : 'Inactive'}
                    />
                ),
            },
            {
                id: 'actions',
                header: 'Actions',
                enableSorting: false,
                cell: ({ row }) => {
                    const role = row.original;

                    return (
                        <div
                            className="flex justify-end gap-1.5"
                            onClick={(event) => event.stopPropagation()}
                        >
                            <ActionIconButton
                                icon={<Pencil />}
                                label={`Edit ${role.name}`}
                                color="edit"
                                disabled={!canEdit}
                                onClick={() => openRow(role, 'edit')}
                            />
                            {!role.is_protected && (
                                <>
                                    <ActionIconButton
                                        icon={<Power />}
                                        label={
                                            role.is_active
                                                ? `Deactivate ${role.name}`
                                                : `Activate ${role.name}`
                                        }
                                        color={role.is_active ? 'danger' : 'view'}
                                        disabled={!canEdit}
                                        onClick={() =>
                                            openRow(role, 'deactivate')
                                        }
                                    />
                                    <ActionIconButton
                                        icon={<Trash2 />}
                                        label={`Delete ${role.name}`}
                                        color="danger"
                                        disabled={!canDelete}
                                        onClick={() =>
                                            openRow(role, 'delete')
                                        }
                                    />
                                </>
                            )}
                        </div>
                    );
                },
            },
        ],
        // `openRow` is redefined every render, so including it would rebuild the
        // columns (and therefore the table) on every keystroke in the search box.
        // Same trade-off the organizations index makes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [canEdit, canDelete],
    );

    const viewedRole = isViewOpen
        ? roles.data.find((role) => role.id === Number(query.get('role')))
        : undefined;

    return (
        <>
            <Head title="Roles" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-3">
                    <Heading
                        title="Roles"
                        description="Platform-wide roles and the central permissions assigned to them."
                    />
                    {canCreate && (
                        <LabelledIconButton
                            icon={<Plus />}
                            label="New role"
                            tooltip="Create role"
                            color="primary"
                            onClick={() => setOverlayParam('create', '1')}
                            className="max-w-20 sm:max-w-24"
                        />
                    )}
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
                                placeholder="Search role names..."
                                aria-label="Search roles"
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
                        data={roles.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        onSortingChange={applySorting}
                        loading={isTableLoading}
                        onRowClick={(role) => openRow(role, 'view')}
                        empty="No roles match your filters."
                    />
                </Card>

                {roles.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {roles.current_page} of {roles.last_page} (
                            {roles.total} roles)
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={roles.current_page <= 1}
                                onClick={() =>
                                    changePage(roles.current_page - 1)
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    roles.current_page >= roles.last_page
                                }
                                onClick={() =>
                                    changePage(roles.current_page + 1)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}

                <p className="text-muted-foreground flex items-center gap-2 text-xs">
                    <UsersRound className="size-4 shrink-0" />
                    <span>
                        Assign a role to a person from the{' '}
                        <a
                            href={usersIndex().url}
                            className="underline underline-offset-2"
                        >
                            users page
                        </a>
                        . Deleting or deactivating a role moves everyone holding
                        it to <strong>searcher</strong>.
                    </span>
                </p>
            </div>

            <ResponsiveOverlay
                open={isCreateOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('create', null, { replace: true });
                    }
                }}
                title="Create role"
                description="Give the role a name and tick the platform permissions it grants."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('create', null, { replace: true })
                        }
                        actions={[
                            {
                                label: 'Create role',
                                form: 'role-create-form',
                                type: 'submit',
                            },
                        ]}
                    />
                }
            >
                <Form
                    id="role-create-form"
                    method="post"
                    action={roleRoutes.store()}
                    onSuccess={() =>
                        setOverlayParam('create', null, { replace: true })
                    }
                    className="grid gap-2 py-2"
                >
                    <div className="grid gap-2 pb-2">
                        <Label htmlFor="role-create-name">Name</Label>
                        <Input
                            id="role-create-name"
                            name="name"
                            placeholder="billing-manager"
                            required
                        />
                        <p className="text-muted-foreground text-xs">
                            Lowercase words separated by hyphens. The name is
                            what code and CSV imports refer to.
                        </p>
                        <FieldError message={errors.name} />
                    </div>

                    <PermissionChecklist
                        idPrefix="create"
                        catalog={permissionCatalog}
                        assigned={[]}
                        errors={errors}
                    />
                </Form>
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isViewOpen && viewedRole !== undefined}
                onOpenChange={(open) => {
                    if (!open) {
                        closeRowSheet();
                    }
                }}
                title={viewedRole?.label ?? 'Role'}
                description={
                    !viewedRole
                        ? 'This role is not on the current page of results.'
                        : rowMode === 'edit'
                          ? 'Assign or revoke the platform-wide permissions granted to this role.'
                          : rowMode === 'delete'
                            ? 'This permanently removes the role.'
                            : rowMode === 'deactivate'
                              ? 'Users holding this role move to searcher.'
                              : 'Details for this role.'
                }
                footer={
                    rowMode === 'edit' && viewedRole ? (
                        <OverlayFooter
                            onCancel={closeRowSheet}
                            actions={[
                                {
                                    label: 'Save permissions',
                                    form: 'role-permissions-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    ) : rowMode === 'delete' && viewedRole ? (
                        <OverlayFooter
                            onCancel={closeRowSheet}
                            actions={[
                                {
                                    label: 'Delete role',
                                    tone: 'danger',
                                    icon: <Trash2 className="size-4" />,
                                    form: 'role-delete-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    ) : rowMode === 'deactivate' && viewedRole ? (
                        <OverlayFooter
                            onCancel={closeRowSheet}
                            actions={[
                                {
                                    label: viewedRole.is_active
                                        ? 'Deactivate'
                                        : 'Activate',
                                    tone: 'danger-ghost',
                                    icon: <Power className="size-4" />,
                                    form: 'role-status-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    ) : viewedRole ? (
                        <OverlayFooter
                            actions={[
                                {
                                    label: 'Edit',
                                    tone: 'secondary',
                                    icon: <Pencil className="size-4" />,
                                    onClick: () =>
                                        openRow(viewedRole, 'edit'),
                                    ...(canEdit ? {} : { disabled: true }),
                                },
                                ...(viewedRole.is_protected
                                    ? []
                                    : [
                                          {
                                              label: viewedRole.is_active
                                                  ? 'Deactivate'
                                                  : 'Activate',
                                              tone: 'danger-ghost' as const,
                                              icon: <Power className="size-4" />,
                                              onClick: () =>
                                                  openRow(
                                                      viewedRole,
                                                      'deactivate',
                                                  ),
                                              ...(canEdit
                                                  ? {}
                                                  : { disabled: true }),
                                          },
                                          {
                                              label: 'Delete',
                                              tone: 'danger-ghost' as const,
                                              icon: (
                                                  <Trash2 className="size-4" />
                                              ),
                                              onClick: () =>
                                                  openRow(
                                                      viewedRole,
                                                      'delete',
                                                  ),
                                              ...(canDelete
                                                  ? {}
                                                  : { disabled: true }),
                                          },
                                      ]),
                            ]}
                        />
                    ) : null
                }
            >
                {viewedRole && rowMode === 'view' && (
                    <RoleDetail role={viewedRole} />
                )}

                {viewedRole && rowMode === 'edit' && (
                    <Form
                        key={viewedRole.id}
                        id="role-permissions-form"
                        method="patch"
                        action={roleRoutes.update({ role: viewedRole.id })}
                        onSuccess={() => closeRowSheet()}
                        className="grid gap-2 py-2"
                    >
                        <PermissionChecklist
                            idPrefix={`edit-${viewedRole.id}`}
                            catalog={permissionCatalog}
                            assigned={viewedRole.permissions.map(
                                (p) => p.value,
                            )}
                            errors={errors}
                        />
                    </Form>
                )}

                {viewedRole &&
                    rowMode === 'deactivate' &&
                    (viewedRole.is_protected ? (
                        <p className="text-sm">
                            <strong>{viewedRole.label}</strong> is a built-in
                            role and cannot be deactivated.
                        </p>
                    ) : (
                        <Form
                            id="role-status-form"
                            method="patch"
                            action={roleRoutes.status({
                                role: viewedRole.id,
                            })}
                            onSuccess={() => closeRowSheet()}
                        >
                            <input
                                type="hidden"
                                name="is_active"
                                value={viewedRole.is_active ? '0' : '1'}
                                readOnly
                            />
                            <p className="text-sm">
                                {viewedRole.is_active ? (
                                    <>
                                        Deactivating{' '}
                                        <strong>{viewedRole.label}</strong>{' '}
                                        revokes its permissions from{' '}
                                        {viewedRole.users_count} user(s), who
                                        move to <strong>searcher</strong>.
                                    </>
                                ) : (
                                    <>
                                        <strong>{viewedRole.label}</strong>{' '}
                                        can be assigned again. Users moved to
                                        searcher are not restored.
                                    </>
                                )}
                            </p>
                        </Form>
                    ))}

                {viewedRole && rowMode === 'delete' && (
                    <Form
                        id="role-delete-form"
                        method="delete"
                        action={roleRoutes.destroy({ role: viewedRole.id })}
                        onSuccess={() =>
                            setOverlayParam(
                                'mode',
                                null,
                                {
                                    replace: true,
                                },
                            )
                        }
                        className="grid gap-3"
                    >
                        <p className="text-sm">
                            This permanently removes{' '}
                            <strong>{viewedRole.label}</strong> and revokes its
                            permissions. {viewedRole.users_count} user(s)
                            holding it move to <strong>searcher</strong>. It
                            cannot be undone.
                        </p>

                        <div className="grid gap-2">
                            <Label htmlFor="role-delete-password">
                                Confirm with your password
                            </Label>
                            <Input
                                id="role-delete-password"
                                name="password"
                                type="password"
                                autoComplete="current-password"
                                required
                            />
                            <FieldError message={errors.password} />
                        </div>
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
                description="Narrow the list of roles."
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
                                            status: '',
                                            type: '',
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
                <div className="grid gap-4 py-2">
                    <fieldset className="space-y-3">
                        <legend className="text-sm font-medium">Status</legend>
                        <RadioGroup
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                refetchTable(
                                    {
                                        search: filters.search,
                                        status: value === 'all' ? '' : value,
                                        type: filters.type,
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
                                { value: 'all', label: 'Any status' },
                                { value: 'active', label: 'Active' },
                                { value: 'inactive', label: 'Inactive' },
                            ].map((option) => (
                                <div
                                    key={option.value}
                                    className="flex items-center gap-2"
                                >
                                    <RadioGroupItem
                                        id={`role-status-${option.value}`}
                                        value={option.value}
                                    />
                                    <Label
                                        htmlFor={`role-status-${option.value}`}
                                        className="cursor-pointer"
                                    >
                                        {option.label}
                                    </Label>
                                </div>
                            ))}
                        </RadioGroup>
                    </fieldset>

                    <fieldset className="space-y-3">
                        <legend className="text-sm font-medium">Type</legend>
                        <RadioGroup
                            value={filters.type || 'all'}
                            onValueChange={(value) =>
                                refetchTable(
                                    {
                                        search: filters.search,
                                        status: filters.status,
                                        type: value === 'all' ? '' : value,
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
                                { value: 'all', label: 'Built-in and custom' },
                                { value: 'default', label: 'Built-in only' },
                                { value: 'custom', label: 'Custom only' },
                            ].map((option) => (
                                <div
                                    key={option.value}
                                    className="flex items-center gap-2"
                                >
                                    <RadioGroupItem
                                        id={`role-type-${option.value}`}
                                        value={option.value}
                                    />
                                    <Label
                                        htmlFor={`role-type-${option.value}`}
                                        className="cursor-pointer"
                                    >
                                        {option.label}
                                    </Label>
                                </div>
                            ))}
                        </RadioGroup>
                    </fieldset>
                </div>
            </ResponsiveOverlay>

            <CsvExportSheet
                open={isExportOpen}
                onClose={() => setOverlayParam('export', null, { replace: true })}
                canExport={canExport}
                total={roles.total}
                pageCount={roles.data.length}
                currentPage={roles.current_page}
                activeFilters={activeFilterSummary}
                onExport={runExport}
            />

            <CsvImportSheet
                open={isImportOpen}
                onClose={() => setOverlayParam('import', null, { replace: true })}
                route={roleRoutes.importMethod}
                idPrefix="roles"
                title="Import roles"
                description="Upload a CSV to create custom roles."
                required={['Name']}
                optional={['Permissions (pipe-separated)', 'Status']}
                canImport={canImport}
                buttonLabel="Import roles"
            />
        </>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="text-xs text-destructive">{message}</p>;
}

/** Read-only summary, for the row sheet's default mode. */
function RoleDetail({ role }: { role: RoleRow }) {
    const rows: [string, string][] = [
        ['Name', role.name],
        ['Type', role.is_protected ? 'Built-in' : 'Custom'],
        ['Status', role.is_active ? 'Active' : 'Inactive'],
        ['Users', String(role.users_count)],
        [
            'Permissions',
            role.permissions.length > 0
                ? role.permissions.map((p) => p.label).join(', ')
                : 'None',
        ],
    ];

    return (
        <dl className="grid gap-3 py-2 text-sm">
            {rows.map(([label, value]) => (
                <div key={label} className="grid grid-cols-[7rem_1fr] gap-2">
                    <dt className="text-muted-foreground">{label}</dt>
                    <dd className="min-w-0 break-words">{value}</dd>
                </div>
            ))}
        </dl>
    );
}

/**
 * The permission picker.
 *
 * The shadcn `Checkbox` renders a `<button>` and submits nothing, so each box
 * is paired with a hidden input carrying its key. One hidden input per *checked*
 * permission is what makes `permissions[]` an array: with nothing ticked there
 * is no input at all, which is exactly what `syncPermissions([])` needs to
 * revoke them all.
 *
 * `idPrefix` keeps the create and edit instances from colliding on ids — both
 * can be mounted, and two elements sharing `id="permission-user.view"` would
 * break the `<label htmlFor>` pairing.
 */
function PermissionChecklist({
    idPrefix,
    catalog,
    assigned,
    errors,
}: {
    idPrefix: string;
    catalog: PermissionRef[];
    assigned: string[];
    errors: Record<string, string>;
}) {
    const [selected, setSelected] = useState<string[]>(assigned);

    return (
        <>
            {catalog.map((permission) => {
                const checked = selected.includes(permission.value);

                return (
                    <div
                        key={permission.value}
                        className="flex items-center gap-3"
                    >
                        <Checkbox
                            id={`${idPrefix}-permission-${permission.value}`}
                            checked={checked}
                            onCheckedChange={() =>
                                setSelected((previous) =>
                                    previous.includes(permission.value)
                                        ? previous.filter(
                                              (p) =>
                                                  p !== permission.value,
                                          )
                                        : [...previous, permission.value],
                                )
                            }
                        />
                        <Label
                            htmlFor={`${idPrefix}-permission-${permission.value}`}
                            className="cursor-pointer"
                        >
                            {permission.label}
                        </Label>
                        {checked && (
                            <input
                                type="hidden"
                                name="permissions[]"
                                value={permission.value}
                                readOnly
                            />
                        )}
                    </div>
                );
            })}

            <FieldError message={errors.permissions} />
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Settings' }, { title: 'Roles' }],
};