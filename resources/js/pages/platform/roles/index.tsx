import { Head, router } from '@inertiajs/react';
import { Form, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Pencil, Shield, UsersRound } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import * as roleRoutes from '@/routes/platform/roles';
import { index as usersIndex } from '@/routes/platform/users';

type PermissionRef = { value: string; label: string };

type RoleRow = {
    id: number;
    name: string;
    permissions: PermissionRef[];
};

type Props = {
    roles: RoleRow[];
    permissionCatalog: PermissionRef[];
    canEdit: boolean;
};

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

export default function RolesIndex({ roles, permissionCatalog, canEdit }: Props) {
    const page = usePage();
    const query = useQuery();
    const errors = (page.props.errors ?? {}) as Record<string, string>;

    const rowMode = query.get('mode') === 'edit' ? 'edit' : 'view';
    const isEditOpen =
        query.get('mode') === 'edit' && Number.isFinite(Number(query.get('role')));

    // There is no search, no filter overlay and no pagination here, and that is
    // deliberate. The platform role set is a fixed enum of four; a toolbar over
    // four rows would hide them behind a click. The only table on the page that
    // could ever be long (the assignable-users list) is gone — role assignment
    // belongs to the users index, which validates against the enum instead of
    // a hardcoded copy of it.
    const columns = useMemo<ColumnDef<RoleRow, unknown>[]>(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: 'Role',
                enableSorting: false,
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <Shield className="size-4 shrink-0 text-muted-foreground" />
                        <span className="font-medium">{row.original.name}</span>
                    </div>
                ),
            },
            {
                id: 'permissions',
                accessorKey: 'permissions',
                header: 'Central permissions',
                enableSorting: false,
                cell: ({ row }) =>
                    row.original.permissions.length === 0 ? (
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
                                onClick={() => openEdit(role.id)}
                            />
                        </div>
                    );
                },
            },
        ],
         
        [canEdit],
    );

    function openEdit(roleId: number) {
        router.get(
            roleRoutes.index().url,
            { mode: 'edit', role: String(roleId) },
            {
                preserveState: true,
                only: ['roles', 'permissionCatalog', 'canEdit'],
            },
        );
    }

    function closeSheet() {
        router.get(
            roleRoutes.index().url,
            {},
            { preserveState: true, replace: true },
        );
    }

    const editedRole = isEditOpen
        ? roles.find((role) => role.id === Number(query.get('role')))
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
                </div>

                {/*
                 * One card = the table's frame. There is no header band because
                 * there is nothing to narrow — see the note above. `gap-0 py-0`
                 * would only matter if a band existed.
                 */}
                <Card className="gap-0 py-0">
                    <DataTable
                        columns={columns}
                        data={roles}
                        getRowId={(row) => String(row.id)}
                        empty="No platform roles found."
                    />
                </Card>

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
                        .
                    </span>
                </p>
            </div>

            <ResponsiveOverlay
                open={isEditOpen && editedRole !== undefined}
                onOpenChange={(open) => {
                    if (!open) {
                        closeSheet();
                    }
                }}
                title={editedRole?.name ?? 'Role'}
                description={
                    rowMode === 'edit'
                        ? 'Assign or revoke the platform-wide permissions granted to this role.'
                        : undefined
                }
                footer={
                    <OverlayFooter
                        onCancel={closeSheet}
                        actions={[
                            {
                                label: 'Save permissions',
                                form: 'role-permissions-form',
                                type: 'submit',
                            },
                        ]}
                    />
                }
            >
                {editedRole && (
                    <Form
                        key={editedRole.id}
                        id="role-permissions-form"
                        method="patch"
                        action={roleRoutes.update({ role: editedRole.id })}
                        onSuccess={() => closeSheet()}
                        className="grid gap-2 py-2"
                    >
                        <PermissionChecklist
                            catalog={permissionCatalog}
                            assigned={editedRole.permissions.map((p) => p.value)}
                            errors={errors}
                        />
                    </Form>
                )}
            </ResponsiveOverlay>
        </>
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
 */
function PermissionChecklist({
    catalog,
    assigned,
    errors,
}: {
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
                            id={`permission-${permission.value}`}
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
                            htmlFor={`permission-${permission.value}`}
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

            {errors.permissions && (
                <p className="text-destructive text-xs">
                    {errors.permissions}
                </p>
            )}
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Settings' }, { title: 'Roles' }],
};
