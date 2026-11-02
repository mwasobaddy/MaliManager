import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    FileText,
    Mail,
    Pencil,
    Phone,
    Plus,
    Trash2,
    Users,
} from 'lucide-react';
import ConfirmDeleteOverlay from '@/components/confirm-delete-overlay';
import Heading from '@/components/heading';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useOverlayParam } from '@/hooks/use-overlay-param';
import {
    agreement as occupantAgreement,
    create as createOccupant,
    destroy as destroyOccupant,
    edit as editOccupant,
    show as showOccupant,
} from '@/routes/tenant/occupants';

type Property = {
    id: number;
    name: string;
    slug: string;
};

type Unit = {
    id: number;
    name: string;
    type: string | null;
    status: string;
};

type Lease = {
    starts_at: string | null;
    rent_amount: string | null;
    rent_frequency: string | null;
    deposit: string | null;
    currency: string | null;
    agreement_document_name: string | null;
    agreement_document_url: string | null;
};

type Occupant = {
    id: number;
    first_name: string;
    last_name: string | null;
    email: string;
    phone: string | null;
    national_id: string | null;
    status: string;
    units: Unit[];
    // Present on the record resolved for the view sheet. List rows omit them
    // because the card does not show them, and the sheet is the only reader.
    gender?: string | null;
    added_at?: string | null;
    lease?: Lease | null;
};

type Props = {
    property: Property;
    occupants: Occupant[];
    focusedOccupant: Occupant | null;
};

export default function OccupantsIndex({
    property,
    occupants,
    focusedOccupant,
}: Props) {
    const { context } = usePage().props;
    const permissions = context?.permissions ?? [];
    const canCreate = permissions.includes('occupant.create');
    const canEdit = permissions.includes('occupant.edit');
    const canDelete = permissions.includes('occupant.delete');

    const { query, setOverlayParam } = useOverlayParam();

    // The delete modal builds its DELETE URL from the row, so the id travels in
    // the URL beside the mode rather than in component state.
    const deleteTargetId = Number(query.get('occupant'));
    const deleteTarget = occupants.find(
        (occupant) => occupant.id === deleteTargetId,
    );
    const isDeleteOpen = query.get('mode') === 'delete' && !!deleteTarget;

    // The record is a sheet on this page, so the id travels in the URL and the
    // server resolves it into `focusedOccupant`. Falling back to the visible
    // rows keeps opening from the list a single request.
    const viewedOccupantId = Number(query.get('occupant'));
    const isViewOpen = query.get('mode') === 'view' && viewedOccupantId > 0;
    const viewedOccupant = isViewOpen
        ? (focusedOccupant ??
          occupants.find((occupant) => occupant.id === viewedOccupantId) ??
          null)
        : null;

    function closeView() {
        setOverlayParam('mode', null, {
            replace: true,
            only: ['occupants'],
            keep: { occupant: '' },
        });
    }

    const money = (
        currency: string | null | undefined,
        amount: string | null,
    ) => (amount === null ? '—' : `${currency ?? ''} ${amount}`.trim());

    return (
        <>
            <Head title={`${property.name} occupants`} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title={`${property.name} occupants`}
                        description="The people renting units in this property."
                    />
                    {canCreate && (
                        <Button asChild>
                            <Link href={createOccupant(property.slug)}>
                                <Plus className="size-4" />
                                Add occupant
                            </Link>
                        </Button>
                    )}
                </div>

                {occupants.length === 0 ? (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <Users className="mx-auto size-10 text-muted-foreground" />
                            <p className="mt-4 font-medium">No occupants yet</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Add your first occupant to assign them to a
                                unit.
                            </p>
                            {canCreate && (
                                <Button asChild className="mt-6">
                                    <Link href={createOccupant(property.slug)}>
                                        Add your first occupant
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {occupants.map((occupant) => (
                            <Card key={occupant.id}>
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="min-w-0">
                                            <CardTitle className="truncate">
                                                <Link
                                                    href={showOccupant({
                                                        property: property.slug,
                                                        occupant: occupant.id,
                                                    })}
                                                    className="hover:underline"
                                                >
                                                    {occupant.first_name}{' '}
                                                    {occupant.last_name}
                                                </Link>
                                            </CardTitle>
                                            <CardDescription className="mt-1 flex flex-col gap-1">
                                                <span className="flex items-center gap-1.5 truncate">
                                                    <Mail className="size-3.5 shrink-0" />
                                                    {occupant.email}
                                                </span>
                                                {occupant.phone && (
                                                    <span className="flex items-center gap-1.5">
                                                        <Phone className="size-3.5 shrink-0" />
                                                        {occupant.phone}
                                                    </span>
                                                )}
                                            </CardDescription>
                                        </div>
                                        <Badge
                                            variant={
                                                occupant.status === 'active'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {occupant.status}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {occupant.national_id && (
                                        <div>
                                            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                                National ID
                                            </p>
                                            <p className="text-sm font-medium">
                                                {occupant.national_id}
                                            </p>
                                        </div>
                                    )}
                                    <div>
                                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                            Units
                                        </p>
                                        {occupant.units.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No units assigned
                                            </p>
                                        ) : (
                                            <div className="mt-1 flex flex-wrap gap-1.5">
                                                {occupant.units.map((unit) => (
                                                    <Badge
                                                        key={unit.id}
                                                        variant="outline"
                                                    >
                                                        {unit.name}
                                                    </Badge>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                    {(canEdit || canDelete) && (
                                        <div className="flex gap-2 pt-1">
                                            {canEdit && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={editOccupant({
                                                            property:
                                                                property.slug,
                                                            occupant:
                                                                occupant.id,
                                                        })}
                                                    >
                                                        Edit
                                                    </Link>
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
                                                                only: [
                                                                    'occupants',
                                                                ],
                                                                keep: {
                                                                    occupant:
                                                                        String(
                                                                            occupant.id,
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

            {viewedOccupant && (
                <ResponsiveOverlay
                    open={isViewOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeView();
                        }
                    }}
                    title={`${viewedOccupant.first_name} ${viewedOccupant.last_name ?? ''}`.trim()}
                    description={`Occupant of ${property.name}.`}
                    footer={
                        <OverlayFooter
                            onCancel={closeView}
                            actions={[
                                ...(viewedOccupant.lease
                                    ? [
                                          {
                                              label: 'Agreement',
                                              tone: 'secondary' as const,
                                              icon: (
                                                  <FileText className="size-4" />
                                              ),
                                              onClick: () =>
                                                  router.visit(
                                                      occupantAgreement({
                                                          property:
                                                              property.slug,
                                                          occupant:
                                                              viewedOccupant.id,
                                                      }).url,
                                                  ),
                                          },
                                      ]
                                    : []),
                                ...(canEdit
                                    ? [
                                          {
                                              label: 'Edit',
                                              icon: (
                                                  <Pencil className="size-4" />
                                              ),
                                              onClick: () =>
                                                  router.visit(
                                                      editOccupant({
                                                          property:
                                                              property.slug,
                                                          occupant:
                                                              viewedOccupant.id,
                                                      }).url,
                                                  ),
                                          },
                                      ]
                                    : []),
                            ]}
                        />
                    }
                >
                    <dl className="grid gap-x-6 gap-y-3 py-2 text-sm sm:grid-cols-2">
                        {(
                            [
                                ['Email', viewedOccupant.email],
                                ['Phone', viewedOccupant.phone ?? '—'],
                                [
                                    'National ID',
                                    viewedOccupant.national_id ?? '—',
                                ],
                                ['Gender', viewedOccupant.gender ?? '—'],
                                ['Added', viewedOccupant.added_at ?? '—'],
                            ] as [string, string][]
                        ).map(([label, value]) => (
                            <div
                                key={label}
                                className="grid grid-cols-[8rem_1fr] gap-2"
                            >
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="min-w-0 break-words">{value}</dd>
                            </div>
                        ))}
                    </dl>

                    <div className="mt-6 space-y-2">
                        <h3 className="text-sm font-medium">
                            Units ({viewedOccupant.units.length})
                        </h3>
                        {viewedOccupant.units.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No units assigned.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border rounded-lg border border-input">
                                {viewedOccupant.units.map((unit) => (
                                    <li
                                        key={unit.id}
                                        className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm"
                                    >
                                        <span className="font-medium">
                                            {unit.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {unit.type ?? '—'}
                                        </span>
                                        <Badge
                                            variant={
                                                unit.status === 'occupied'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {unit.status}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="mt-6 space-y-2">
                        <h3 className="text-sm font-medium">Active lease</h3>
                        {viewedOccupant.lease ? (
                            <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                                {(
                                    [
                                        [
                                            'Starts',
                                            viewedOccupant.lease.starts_at ??
                                                '—',
                                        ],
                                        [
                                            'Rent',
                                            `${money(
                                                viewedOccupant.lease.currency,
                                                viewedOccupant.lease
                                                    .rent_amount,
                                            )}${
                                                viewedOccupant.lease
                                                    .rent_frequency
                                                    ? ` / ${viewedOccupant.lease.rent_frequency}`
                                                    : ''
                                            }`,
                                        ],
                                        [
                                            'Deposit',
                                            money(
                                                viewedOccupant.lease.currency,
                                                viewedOccupant.lease.deposit,
                                            ),
                                        ],
                                    ] as [string, string][]
                                ).map(([label, value]) => (
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

                                <div className="grid grid-cols-[8rem_1fr] gap-2">
                                    <dt className="text-muted-foreground">
                                        Agreement
                                    </dt>
                                    <dd className="min-w-0 break-words">
                                        {viewedOccupant.lease
                                            .agreement_document_url ? (
                                            <a
                                                href={
                                                    viewedOccupant.lease
                                                        .agreement_document_url
                                                }
                                                className="underline underline-offset-4"
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                {viewedOccupant.lease
                                                    .agreement_document_name ??
                                                    'Download'}
                                            </a>
                                        ) : (
                                            'None uploaded'
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No active lease in this property.
                            </p>
                        )}
                    </div>
                </ResponsiveOverlay>
            )}

            {canDelete && deleteTarget && (
                <ConfirmDeleteOverlay
                    open={isDeleteOpen}
                    onOpenChange={(open) =>
                        !open &&
                        setOverlayParam('mode', null, {
                            replace: true,
                            only: ['occupants'],
                            keep: { occupant: '' },
                        })
                    }
                    action={
                        destroyOccupant({
                            property: property.slug,
                            occupant: deleteTarget.id,
                        }).url
                    }
                    title={`Remove ${deleteTarget.first_name} ${deleteTarget.last_name ?? ''}`.trim()}
                    description="Their units are released and the occupant record is removed. Confirm with your password — this cannot be undone from the interface."
                />
            )}
        </>
    );
}

OccupantsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: '',
        },
        {
            title: 'Occupants',
        },
    ],
};
