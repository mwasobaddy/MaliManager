import { Form, Head, router, usePage } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    Plus,
    Search,
    Shield,
    SquarePen,
    Trash2,
    UsersRound,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import ConfirmDeleteOverlay from '@/components/confirm-delete-overlay';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useOverlayParam } from '@/hooks/use-overlay-param';
import * as roleRoutes from '@/routes/tenant/roles';
import { update as updateSecurityPolicy } from '@/routes/tenant/security-policy';

type PermissionRef = { value: string; label: string };

type PermissionGroup = {
    module: string;
    module_label: string;
    permissions: PermissionRef[];
};

type SubRoleRow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_locked: boolean;
    members_count: number;
    permissions: PermissionRef[];
};

type Props = {
    roles: {
        data: SubRoleRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    permissionCatalog: PermissionGroup[];
    security: {
        require_staff_two_factor: boolean;
        is_owner: boolean;
    };
};

/** Debounce so a fast typist issues one request, not one per keystroke. */
const SEARCH_DEBOUNCE_MS = 350;

function SecurityPolicyCard({
    security,
}: {
    security: {
        require_staff_two_factor: boolean;
        is_owner: boolean;
    };
}) {
    const [required, setRequired] = useState(security.require_staff_two_factor);

    return (
        <Card className="p-4">
            <Form {...updateSecurityPolicy.form()}>
                {({ processing }) => (
                    <div className="flex items-center justify-between gap-4">
                        <Label
                            htmlFor="require_staff_two_factor"
                            className="flex-1 cursor-pointer"
                        >
                            <span className="block text-sm font-medium">
                                Require two-factor authentication for staff
                            </span>
                            <span className="block text-sm text-muted-foreground">
                                Staff without two-factor enabled are sent to set
                                it up at their next sign-in. Owners are never
                                blocked.
                            </span>
                        </Label>
                        <Checkbox
                            id="require_staff_two_factor"
                            checked={required}
                            onCheckedChange={(checked) =>
                                setRequired(checked === true)
                            }
                        />
                        <input
                            type="hidden"
                            name="require_staff_two_factor"
                            value={required ? '1' : '0'}
                        />
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Save policy
                        </Button>
                    </div>
                )}
            </Form>
        </Card>
    );
}

export default function StaffRolesIndex({
    roles,
    permissionCatalog,
    security,
}: Props) {
    const { query, setOverlayParam } = useOverlayParam();
    const pageUrl = usePage().url;

    const filters = {
        search: query.get('search') ?? '',
        type: query.get('type') ?? '',
    };

    const sort = {
        column: query.get('sort') || null,
        direction: query.get('direction') === 'asc' ? 'asc' : 'desc',
    };

    const only = ['roles', 'permissionCatalog', 'security'];

    function navigate(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        router.get(pageUrl.split('?')[0], params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only,
        });
    }

    /** Replaces the visible rows, so the table shows a skeleton in flight. */
    const [isTableLoading, setIsTableLoading] = useState(false);

    function refetchTable(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        setIsTableLoading(true);

        router.get(pageUrl.split('?')[0], params, {
            preserveState: true,
            replace: options?.replace ?? false,
            only,
            onFinish: () => setIsTableLoading(false),
        });
    }

    const isCreateOpen = query.get('create') === '1';
    const roleParam = query.get('role');
    const viewedRoleId = Number(roleParam);
    const isViewOpen =
        roleParam !== null && Number.isFinite(viewedRoleId) && viewedRoleId > 0;
    const viewedRole = isViewOpen
        ? (roles.data.find((role) => role.id === viewedRoleId) ?? null)
        : null;
    const rowMode = query.get('mode');
    const isEditOpen = viewedRole !== null && rowMode === 'edit';
    const isDeleteOpen = viewedRole !== null && rowMode === 'delete';

    function openCreate() {
        setOverlayParam('create', '1');
    }

    function openEdit(role: SubRoleRow) {
        if (role.is_locked) {
            return;
        }
        setOverlayParam('mode', 'edit', { keep: { role: String(role.id) } });
    }

    function openView(role: SubRoleRow) {
        setOverlayParam('mode', 'view', { keep: { role: String(role.id) } });
    }

    function openDelete(role: SubRoleRow) {
        if (role.is_locked) {
            return;
        }
        setOverlayParam('mode', 'delete', { keep: { role: String(role.id) } });
    }

    // Close returns to the filtered list, not page one: dropping the
    // search would strand the landlord away from the rows they came from.
    function closeSheets() {
        navigate(
            {
                search: filters.search,
                type: filters.type,
                sort: sort.column ?? '',
                direction: sort.direction,
                page: roles.current_page,
            },
            { replace: true },
        );
    }

    function applySorting(next: SortingState) {
        const first = next[0];

        refetchTable(
            {
                search: filters.search,
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
                type: filters.type,
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

    // Keep the box in step with Back/Forward.
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

    const columns = useMemo<ColumnDef<SubRoleRow, unknown>[]>(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: 'Role',
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <Shield className="size-4 shrink-0 text-muted-foreground" />
                        <span className="font-medium">{row.original.name}</span>
                        {row.original.is_locked && (
                            <Badge variant="neutral">Built-in</Badge>
                        )}
                    </div>
                ),
            },
            {
                id: 'permissions',
                header: 'Permissions',
                enableSorting: false,
                cell: ({ row }) =>
                    row.original.is_locked ? (
                        <span className="text-xs text-muted-foreground">
                            Every permission
                        </span>
                    ) : row.original.permissions.length === 0 ? (
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
                            {row.original.permissions.length}{' '}
                            {row.original.permissions.length === 1
                                ? 'permission'
                                : 'permissions'}
                        </span>
                    ),
            },
            {
                id: 'members',
                accessorKey: 'members_count',
                header: 'Members',
                cell: ({ row }) => (
                    <span className="text-xs tabular-nums">
                        {row.original.members_count}
                    </span>
                ),
            },
            {
                id: 'actions',
                header: 'Actions',
                enableSorting: false,
                meta: { align: 'right' },
                cell: ({ row }) => {
                    const role = row.original;

                    return (
                        <div
                            className="flex justify-end gap-1"
                            onClick={(event) => event.stopPropagation()}
                        >
                            <ActionIconButton
                                icon={<SquarePen />}
                                label={`Edit ${role.name}`}
                                color="edit"
                                disabled={role.is_locked}
                                onClick={() => openEdit(role)}
                            />
                            {!role.is_locked && (
                                <ActionIconButton
                                    icon={<Trash2 />}
                                    label={`Delete ${role.name}`}
                                    color="danger"
                                    onClick={() => openDelete(role)}
                                />
                            )}
                        </div>
                    );
                },
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Roles" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        className="min-w-0 flex-1"
                        level="h1"
                        title="Staff roles"
                        description="Named permission bundles: shape caretaker and agent, or create narrower roles."
                    />
                    <LabelledIconButton
                        icon={<Plus />}
                        label="New role"
                        tooltip="Create a staff role"
                        color="primary"
                        onClick={openCreate}
                        className="ml-auto max-w-20 sm:max-w-24"
                    />
                </div>

                {security.is_owner ? (
                    <SecurityPolicyCard
                        key={String(security.require_staff_two_factor)}
                        security={security}
                    />
                ) : null}

                <Card className="gap-0 py-0">
                    <div className="flex flex-wrap items-center gap-2 border-b border-border-strong p-4">
                        <div className="relative min-w-56 flex-1">
                            <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={searchDraft}
                                onChange={(event) =>
                                    setSearchDraft(event.target.value)
                                }
                                placeholder="Search roles..."
                                aria-label="Search roles"
                                className="border-border-strong bg-background pr-3 pl-9"
                            />
                        </div>
                        <Select
                            value={filters.type || 'all'}
                            onValueChange={(value) =>
                                refetchTable(
                                    {
                                        search: filters.search,
                                        type: value === 'all' ? '' : value,
                                        sort: sort.column ?? '',
                                        direction: sort.direction,
                                    },
                                    { replace: true },
                                )
                            }
                        >
                            <SelectTrigger
                                aria-label="Filter by type"
                                className="w-40"
                            >
                                <SelectValue placeholder="All types" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Built-in and custom
                                </SelectItem>
                                <SelectItem value="builtin">
                                    Built-in only
                                </SelectItem>
                                <SelectItem value="custom">
                                    Custom only
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <DataTable
                        columns={columns}
                        data={roles.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        onSortingChange={applySorting}
                        loading={isTableLoading}
                        onRowClick={(role) => openView(role)}
                        empty={
                            filters.search || filters.type
                                ? 'No roles match your filters.'
                                : 'No roles yet.'
                        }
                    />
                </Card>

                <Pagination
                    currentPage={roles.current_page}
                    lastPage={roles.last_page}
                    total={roles.total}
                    label="roles"
                    onChange={changePage}
                />

                <p className="flex items-center gap-2 text-xs text-muted-foreground">
                    <UsersRound className="size-4 shrink-0" />
                    <span>
                        Assign a role to a person from the staff list. The
                        landlord role always holds every permission and cannot
                        be changed or deleted.
                    </span>
                </p>
            </div>

            <ResponsiveOverlay
                open={isCreateOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        closeSheets();
                    }
                }}
                title="Create role"
                description="Name it, then tick exactly what holders may do."
                footer={
                    <OverlayFooter
                        onCancel={closeSheets}
                        actions={[
                            {
                                label: 'Create role',
                                form: 'sub-role-create-form',
                                type: 'submit',
                            },
                        ]}
                    />
                }
            >
                <Form
                    id="sub-role-create-form"
                    {...roleRoutes.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={closeSheets}
                    className="grid gap-4 py-2"
                >
                    {({ errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="role-create-name">Name</Label>
                                <Input
                                    id="role-create-name"
                                    name="name"
                                    placeholder="Plumber"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="role-create-description">
                                    Description{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <Textarea
                                    id="role-create-description"
                                    name="description"
                                    placeholder="Maintains the buildings, nothing else."
                                />
                                <InputError message={errors.description} />
                            </div>
                            <PermissionChecklist
                                idPrefix="create"
                                catalog={permissionCatalog}
                                assigned={[]}
                                errors={errors}
                            />
                        </>
                    )}
                </Form>
            </ResponsiveOverlay>

            {isViewOpen && !viewedRole && !isDeleteOpen && (
                <ResponsiveOverlay
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            closeSheets();
                        }
                    }}
                    title="Role"
                    description="This role is not on the current page of results."
                    footer={
                        <OverlayFooter
                            onCancel={closeSheets}
                            cancelLabel="Close"
                            actions={[]}
                        />
                    }
                >
                    <p className="py-2 text-sm text-muted-foreground">
                        Clear the search or change pages to find it.
                    </p>
                </ResponsiveOverlay>
            )}

            {viewedRole && !isDeleteOpen && (
                <ResponsiveOverlay
                    open={viewedRole !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeSheets();
                        }
                    }}
                    title={viewedRole.name}
                    description={
                        viewedRole.is_locked
                            ? 'The landlord role always holds every permission.'
                            : isEditOpen
                              ? 'Rename it or re-tick what holders may do.'
                              : 'What this role grants its holders.'
                    }
                    footer={
                        isEditOpen && !viewedRole.is_locked ? (
                            <OverlayFooter
                                onCancel={closeSheets}
                                actions={[
                                    {
                                        label: 'Save permissions',
                                        form: 'sub-role-edit-form',
                                        type: 'submit',
                                    },
                                ]}
                            />
                        ) : !viewedRole.is_locked && !isEditOpen ? (
                            <OverlayFooter
                                onCancel={closeSheets}
                                actions={[
                                    {
                                        label: 'Edit',
                                        icon: <SquarePen className="size-4" />,
                                        onClick: () => openEdit(viewedRole),
                                    },
                                ]}
                            />
                        ) : undefined
                    }
                >
                    {isEditOpen && !viewedRole.is_locked ? (
                        <Form
                            key={viewedRole.id}
                            id="sub-role-edit-form"
                            {...roleRoutes.update.form(viewedRole.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={closeSheets}
                            className="grid gap-4 py-2"
                        >
                            {({ errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`role-edit-name-${viewedRole.id}`}
                                        >
                                            Name
                                        </Label>
                                        <Input
                                            id={`role-edit-name-${viewedRole.id}`}
                                            name="name"
                                            defaultValue={viewedRole.name}
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`role-edit-description-${viewedRole.id}`}
                                        >
                                            Description{' '}
                                            <span className="font-normal text-muted-foreground">
                                                (optional)
                                            </span>
                                        </Label>
                                        <Textarea
                                            id={`role-edit-description-${viewedRole.id}`}
                                            name="description"
                                            defaultValue={
                                                viewedRole.description ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.description}
                                        />
                                    </div>
                                    <PermissionChecklist
                                        idPrefix={`edit-${viewedRole.id}`}
                                        catalog={permissionCatalog}
                                        assigned={viewedRole.permissions.map(
                                            (p) => p.value,
                                        )}
                                        errors={errors}
                                    />
                                </>
                            )}
                        </Form>
                    ) : (
                        <div className="grid gap-4 py-2">
                            {viewedRole.description && (
                                <p className="text-sm text-muted-foreground">
                                    {viewedRole.description}
                                </p>
                            )}
                            {viewedRole.is_locked ? (
                                <p className="text-sm">
                                    Holds every permission, for every present
                                    and future feature. Assign it only to
                                    owners.
                                </p>
                            ) : (
                                <div className="grid gap-3">
                                    {permissionCatalog.map((group) => {
                                        const held =
                                            viewedRole.permissions.filter((p) =>
                                                group.permissions.some(
                                                    (g) => g.value === p.value,
                                                ),
                                            );

                                        if (held.length === 0) {
                                            return null;
                                        }

                                        return (
                                            <div
                                                key={group.module}
                                                className="grid gap-1"
                                            >
                                                <p className="text-sm font-medium">
                                                    {group.module_label}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {held
                                                        .map((p) => p.label)
                                                        .join(', ')}
                                                </p>
                                            </div>
                                        );
                                    })}
                                    {viewedRole.permissions.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            No permissions — holders can sign in
                                            but change nothing.
                                        </p>
                                    )}
                                </div>
                            )}
                            {!viewedRole.is_locked && (
                                <div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => openEdit(viewedRole)}
                                    >
                                        <SquarePen className="size-4" />
                                        Edit role
                                    </Button>
                                </div>
                            )}
                        </div>
                    )}
                </ResponsiveOverlay>
            )}

            {viewedRole && !viewedRole.is_locked && (
                <ConfirmDeleteOverlay
                    open={isDeleteOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeSheets();
                        }
                    }}
                    action={roleRoutes.destroy(viewedRole.id).url}
                    title={`Delete ${viewedRole.name}?`}
                    description={
                        viewedRole.members_count > 0
                            ? `Reassign the ${viewedRole.members_count} staff member(s) holding it first — a role with holders cannot be deleted.`
                            : 'Its permission set goes with it. This cannot be undone.'
                    }
                    onSuccess={closeSheets}
                />
            )}
        </>
    );
}

/**
 * Grouped permission picker. The Checkbox renders a `<button>` and submits
 * nothing, so each ticked box is paired with a hidden input: one hidden
 * input per checked permission is what makes `permissions[]` an array, and
 * nothing ticked posts nothing, which syncs to an empty set.
 */
function PermissionChecklist({
    idPrefix,
    catalog,
    assigned,
    errors,
}: {
    idPrefix: string;
    catalog: PermissionGroup[];
    assigned: string[];
    errors: Record<string, string>;
}) {
    const [selected, setSelected] = useState<string[]>(assigned);

    return (
        <>
            {catalog.map((group) => (
                <fieldset key={group.module} className="grid gap-2">
                    <legend className="text-sm font-medium">
                        {group.module_label}
                    </legend>
                    {group.permissions.map((permission) => {
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
                                                          p !==
                                                          permission.value,
                                                  )
                                                : [
                                                      ...previous,
                                                      permission.value,
                                                  ],
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
                </fieldset>
            ))}

            <InputError message={errors.permissions} />
        </>
    );
}

StaffRolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles' }],
};
