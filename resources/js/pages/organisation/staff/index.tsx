import { Head, router, usePage } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import { Mail, Phone, Plus, Trash2, UserCog } from 'lucide-react';
import { useEffect } from 'react';
import ConfirmDeleteOverlay from '@/components/confirm-delete-overlay';
import Heading from '@/components/heading';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import StaffFormFields from '@/components/staff-form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useOverlayParam } from '@/hooks/use-overlay-param';
import {
    destroy as destroyStaff,
    store as storeStaff,
    update as updateStaff,
} from '@/routes/tenant/staff';

type Organization = {
    id: number;
    name: string;
    slug: string;
};

type SubRole = {
    id: number;
    name: string;
    slug: string;
};

type Property = {
    id: number;
    name: string;
    slug: string;
};

type StaffMember = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    status: string;
    sub_role: SubRole | null;
    properties: Property[];
};

type FocusedStaff = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    sub_role_id: number | null;
    property_ids: number[];
};

type Props = {
    organization: Organization;
    staff: StaffMember[];
    sub_roles?: SubRole[];
    properties?: Property[];
    focusedStaff?: FocusedStaff | null;
};

export default function StaffIndex({
    organization,
    staff,
    sub_roles,
    properties,
    focusedStaff,
}: Props) {
    const { context } = usePage().props;
    const permissions = context?.permissions ?? [];
    const canCreate = permissions.includes('staff.create');
    const canEdit = permissions.includes('staff.edit');
    const canDelete = permissions.includes('staff.delete');

    const { query, setOverlayParam } = useOverlayParam();

    // The delete modal needs the row's identity to build its DELETE URL, so the
    // id travels in the URL beside the mode instead of in component state.
    const deleteTargetId = Number(query.get('staff'));
    const deleteTarget = staff.find((member) => member.id === deleteTargetId);
    const isDeleteOpen = query.get('mode') === 'delete' && !!deleteTarget;

    const isCreateOpen = query.get('create') === '1';

    // Edit resolves from the visible rows, falling back to the server-fetched
    // row so a shared link to an off-page member still opens.
    const editTargetId = Number(query.get('staff'));
    const editRow = staff.find((member) => member.id === editTargetId);
    const editTarget =
        editRow !== undefined
            ? {
                  id: editRow.id,
                  name: editRow.name,
                  email: editRow.email,
                  phone: editRow.phone,
                  sub_role_id: editRow.sub_role?.id ?? null,
                  property_ids: editRow.properties.map(
                      (property) => property.id,
                  ),
              }
            : focusedStaff?.id === editTargetId
              ? focusedStaff
              : undefined;
    const isEditOpen = query.get('mode') === 'edit' && editTarget !== undefined;

    // The option lists are heavy enough to skip until a sheet actually opens.
    function ensureFormOptions() {
        if (sub_roles === undefined || properties === undefined) {
            router.reload({ only: ['sub_roles', 'properties'] });
        }
    }

    // Direct links (?create=1, shared URLs) open the sheet without a button
    // click, so the options also load on mount when a form sheet is open.
    useEffect(() => {
        if (
            (query.get('create') === '1' || query.get('mode') === 'edit') &&
            (sub_roles === undefined || properties === undefined)
        ) {
            router.reload({ only: ['sub_roles', 'properties'] });
        }
    }, [query, sub_roles, properties]);

    function openCreate() {
        ensureFormOptions();
        setOverlayParam('create', '1', {
            only: ['staff', 'sub_roles', 'properties'],
        });
    }

    function openEdit(member: StaffMember) {
        ensureFormOptions();
        setOverlayParam('mode', 'edit', {
            keep: { staff: String(member.id) },
        });
    }

    function closeSheets() {
        setOverlayParam('mode', null, {
            replace: true,
            only: ['staff'],
            keep: { staff: '', create: '' },
        });
    }

    return (
        <>
            <Head title="Staff" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        level="h1"
                        variant="small"
                        title={`${organization.name} staff`}
                        description="Manage the people who run your properties."
                    />
                    {canCreate && (
                        <Button type="button" onClick={openCreate}>
                            <Plus className="size-4" />
                            Add staff
                        </Button>
                    )}
                </div>

                {staff.length === 0 ? (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <UserCog className="mx-auto size-10 text-muted-foreground" />
                            <p className="mt-4 font-medium">No staff yet</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Add your first staff member to assign properties
                                and roles.
                            </p>
                            {canCreate && (
                                <Button
                                    type="button"
                                    className="mt-6"
                                    onClick={openCreate}
                                >
                                    Add your first staff member
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {staff.map((member) => (
                            <Card key={member.id}>
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="min-w-0">
                                            <CardTitle className="truncate">
                                                {member.name}
                                            </CardTitle>
                                            <CardDescription className="mt-1 flex flex-col gap-1">
                                                <span className="flex items-center gap-1.5 truncate">
                                                    <Mail className="size-3.5 shrink-0" />
                                                    {member.email}
                                                </span>
                                                {member.phone && (
                                                    <span className="flex items-center gap-1.5">
                                                        <Phone className="size-3.5 shrink-0" />
                                                        {member.phone}
                                                    </span>
                                                )}
                                            </CardDescription>
                                        </div>
                                        <Badge
                                            variant={
                                                member.status === 'active'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {member.status}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div>
                                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                            Role
                                        </p>
                                        <p className="text-sm font-medium">
                                            {member.sub_role?.name ??
                                                'No role assigned'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                            Manages
                                        </p>
                                        {member.properties.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No properties assigned
                                            </p>
                                        ) : (
                                            <div className="mt-1 flex flex-wrap gap-1.5">
                                                {member.properties.map(
                                                    (property) => (
                                                        <Badge
                                                            key={property.id}
                                                            variant="outline"
                                                        >
                                                            {property.name}
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                    </div>
                                    {(canEdit || canDelete) && (
                                        <div className="flex gap-2 pt-1">
                                            {canEdit && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        openEdit(member)
                                                    }
                                                >
                                                    Edit
                                                </Button>
                                            )}
                                            {canDelete && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-status-danger"
                                                    onClick={() =>
                                                        setOverlayParam(
                                                            'mode',
                                                            'delete',
                                                            {
                                                                only: ['staff'],
                                                                keep: {
                                                                    staff: String(
                                                                        member.id,
                                                                    ),
                                                                },
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                    Delete
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {canDelete && deleteTarget && (
                <ConfirmDeleteOverlay
                    open={isDeleteOpen}
                    onOpenChange={(open) =>
                        !open &&
                        setOverlayParam('mode', null, {
                            replace: true,
                            only: ['staff'],
                            keep: { staff: '' },
                        })
                    }
                    action={destroyStaff(deleteTarget.id).url}
                    title={`Remove ${deleteTarget.name}?`}
                    description="They lose access to this organization and its properties immediately. Confirm with your password — this cannot be undone from the interface."
                />
            )}

            {canCreate && (
                <ResponsiveOverlay
                    open={isCreateOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeSheets();
                        }
                    }}
                    title="Add a staff member"
                    description={`Give ${organization.name} someone to manage its properties.`}
                    footer={
                        <OverlayFooter
                            onCancel={closeSheets}
                            actions={[
                                {
                                    label: 'Add staff member',
                                    form: 'staff-create-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    }
                >
                    {sub_roles === undefined || properties === undefined ? (
                        <div className="flex justify-center py-8">
                            <Spinner />
                        </div>
                    ) : (
                        <Form
                            id="staff-create-form"
                            {...storeStaff.form()}
                            onSuccess={() => closeSheets()}
                            className="space-y-6"
                        >
                            {({ errors }) => (
                                <StaffFormFields
                                    sub_roles={sub_roles}
                                    properties={properties}
                                    errors={errors}
                                    idPrefix="create"
                                    propertiesHint="Select the properties this staff member can manage. They will land directly on a single assigned property after login."
                                />
                            )}
                        </Form>
                    )}
                </ResponsiveOverlay>
            )}

            {canEdit && editTarget && (
                <ResponsiveOverlay
                    open={isEditOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeSheets();
                        }
                    }}
                    title={`Edit ${editTarget.name}`}
                    description={`Update ${organization.name} staff member details and assignments.`}
                    footer={
                        <OverlayFooter
                            onCancel={closeSheets}
                            actions={[
                                {
                                    label: 'Save changes',
                                    form: 'staff-edit-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    }
                >
                    {sub_roles === undefined || properties === undefined ? (
                        <div className="flex justify-center py-8">
                            <Spinner />
                        </div>
                    ) : (
                        <Form
                            key={editTarget.id}
                            id="staff-edit-form"
                            {...updateStaff.form(editTarget.id)}
                            onSuccess={() => closeSheets()}
                            className="space-y-6"
                        >
                            {({ errors }) => (
                                <StaffFormFields
                                    key={editTarget.id}
                                    sub_roles={sub_roles}
                                    properties={properties}
                                    errors={errors}
                                    idPrefix="edit"
                                    defaults={{
                                        name: editTarget.name,
                                        email: editTarget.email,
                                        phone: editTarget.phone,
                                        sub_role_id: editTarget.sub_role_id,
                                        property_ids: editTarget.property_ids,
                                    }}
                                />
                            )}
                        </Form>
                    )}
                </ResponsiveOverlay>
            )}
        </>
    );
}

StaffIndex.layout = {
    breadcrumbs: [
        {
            title: 'Staff',
        },
    ],
};
