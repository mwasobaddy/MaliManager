import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Building2, Download, Pencil } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    agreement as occupantAgreement,
    edit as editOccupant,
    index as occupantsIndex,
} from '@/routes/tenant/occupants';

type Organization = {
    id: number;
    name: string;
    slug: string;
};

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
    gender: string | null;
    status: string;
    added_at: string | null;
    units: Unit[];
    lease: Lease | null;
};

type Props = {
    organization: Organization;
    property: Property;
    occupant: Occupant;
};

export default function OccupantShow({ property, occupant }: Props) {
    const { context } = usePage().props;
    const permissions = context?.permissions ?? [];
    const canEdit = permissions.includes('occupant.edit');

    const name = [occupant.first_name, occupant.last_name]
        .filter(Boolean)
        .join(' ');

    const details: [string, string][] = [
        ['Email', occupant.email],
        ['Phone', occupant.phone ?? '—'],
        ['National ID', occupant.national_id ?? '—'],
        ['Gender', occupant.gender ?? '—'],
        ['Status', occupant.status],
        ['Added', occupant.added_at ?? '—'],
    ];

    const money = (amount: string | null) =>
        amount === null
            ? '—'
            : `${occupant.lease?.currency ?? ''} ${amount}`.trim();

    const lease: [string, string][] | null = occupant.lease
        ? [
              ['Starts', occupant.lease.starts_at ?? '—'],
              [
                  'Rent',
                  `${money(occupant.lease.rent_amount)}${
                      occupant.lease.rent_frequency
                          ? ` / ${occupant.lease.rent_frequency}`
                          : ''
                  }`,
              ],
              ['Deposit', money(occupant.lease.deposit)],
          ]
        : null;

    return (
        <>
            <Head title={name} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Heading
                            title={name}
                            description={`Occupant of ${property.name}.`}
                        />
                        <p className="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                            <Building2 className="size-4 shrink-0" />
                            {property.name}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {canEdit && (
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={editOccupant({
                                        property: property.slug,
                                        occupant: occupant.id,
                                    })}
                                >
                                    <Pencil className="size-4" />
                                    Edit
                                </Link>
                            </Button>
                        )}
                        {canEdit && occupant.lease && (
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={occupantAgreement({
                                        property: property.slug,
                                        occupant: occupant.id,
                                    })}
                                >
                                    <Download className="size-4" />
                                    Agreement
                                </Link>
                            </Button>
                        )}
                        <Link
                            href={occupantsIndex({ property: property.slug })}
                            className="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" />
                            All occupants
                        </Link>
                    </div>
                </div>

                <Card className="gap-0 py-0">
                    <div className="grid gap-x-6 gap-y-3 p-4 text-sm sm:grid-cols-2">
                        {details.map(([label, value]) => (
                            <div
                                key={label}
                                className="grid grid-cols-[9rem_1fr] gap-2"
                            >
                                <span className="text-muted-foreground">
                                    {label}
                                </span>
                                <span className="min-w-0 break-words">
                                    {label === 'Status' ? (
                                        <Badge
                                            variant={
                                                value === 'active'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {value}
                                        </Badge>
                                    ) : (
                                        value
                                    )}
                                </span>
                            </div>
                        ))}
                    </div>
                </Card>

                <Card className="gap-0 py-0">
                    <div className="border-b border-border-strong p-4">
                        <h2 className="font-medium">
                            Units ({occupant.units.length})
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Units in {property.name} this occupant holds.
                        </p>
                    </div>

                    {occupant.units.length === 0 ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            No units assigned.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {occupant.units.map((unit) => (
                                <li
                                    key={unit.id}
                                    className="flex flex-wrap items-center justify-between gap-3 p-4 text-sm"
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
                </Card>

                <Card className="gap-0 py-0">
                    <div className="border-b border-border-strong p-4">
                        <h2 className="font-medium">Active lease</h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            The lease running in {property.name}. Its full text
                            is on the agreement page.
                        </p>
                    </div>

                    {lease === null ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            No active lease in this property.
                        </p>
                    ) : (
                        <div className="grid gap-x-6 gap-y-3 p-4 text-sm sm:grid-cols-2">
                            {lease.map(([label, value]) => (
                                <div
                                    key={label}
                                    className="grid grid-cols-[9rem_1fr] gap-2"
                                >
                                    <span className="text-muted-foreground">
                                        {label}
                                    </span>
                                    <span className="min-w-0 break-words">
                                        {value}
                                    </span>
                                </div>
                            ))}

                            <div className="grid grid-cols-[9rem_1fr] gap-2">
                                <span className="text-muted-foreground">
                                    Agreement document
                                </span>
                                <span className="min-w-0 break-words">
                                    {occupant.lease?.agreement_document_url ? (
                                        <a
                                            href={
                                                occupant.lease
                                                    .agreement_document_url
                                            }
                                            className="underline underline-offset-4"
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            {occupant.lease
                                                .agreement_document_name ??
                                                'Download'}
                                        </a>
                                    ) : (
                                        'None uploaded'
                                    )}
                                </span>
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}

OccupantShow.layout = {
    breadcrumbs: [
        {
            title: 'Occupants',
        },
        {
            title: 'Occupant',
        },
    ],
};
