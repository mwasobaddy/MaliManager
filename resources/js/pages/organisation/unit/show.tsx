import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Building2, ImageOff } from 'lucide-react';
import { Empty } from '@/components/ui/empty';
import Heading from '@/components/heading';
import { Card } from '@/components/ui/card';
import { formatMoney } from '@/lib/format-money';
import { index as unitsIndex } from '@/routes/tenant/units';

type Unit = {
    id: number;
    name: string;
    slug: string;
    type: string | null;
    status: string;
    rent_amount: string | null;
    rent_frequency: string | null;
    deposit: string | null;
    photos: { id: number; url: string; name: string }[];
    property: { id: number | null; name: string | null; slug: string | null };
};

type Props = {
    unit: Unit;
};

export default function UnitShow({ unit }: Props) {
    const details: [string, string][] = [
        ['Name', unit.name],
        ['Reference', unit.slug],
        ['Type', unit.type ?? '—'],
        ['Status', unit.status],
        [
            'Rent amount',
            `${formatMoney(unit.rent_amount)}${unit.rent_frequency === 'daily' ? ' / day' : unit.rent_frequency === 'weekly' ? ' / week' : unit.rent_frequency === 'yearly' ? ' / year' : ' / month'}`,
        ],
        ['Deposit', formatMoney(unit.deposit)],
    ];

    return (
        <>
            <Head title={`Unit ${unit.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <Heading
                            level="h1"
                            title={`Unit ${unit.name}`}
                            description="Photos and letting details for this unit."
                        />
                        {unit.property.slug && (
                            <p className="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                                <Building2 className="size-4 shrink-0" />
                                {unit.property.name}
                            </p>
                        )}
                    </div>
                    <Link
                        href={unitsIndex()}
                        className="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                        All units
                    </Link>
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
                                <span className="min-w-0 break-words capitalize">
                                    {value}
                                </span>
                            </div>
                        ))}
                    </div>
                </Card>

                <Card className="gap-0 py-0">
                    <div className="border-b border-border-strong p-4">
                        <h2 className="font-medium">
                            Photos ({unit.photos.length})
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Up to 12 photos • JPG, PNG or WebP.
                        </p>
                    </div>

                    {unit.photos.length === 0 ? (
                        <Empty
                            compact
                            icon={<ImageOff className="size-4" />}
                            title="No photos yet"
                            description="Add them when creating the unit."
                        />
                    ) : (
                        <div className="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 lg:grid-cols-4">
                            {unit.photos.map((photo) => (
                                <a
                                    key={photo.id}
                                    href={photo.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="group overflow-hidden rounded-lg border border-border-strong"
                                >
                                    <img
                                        src={photo.url}
                                        alt={photo.name}
                                        loading="lazy"
                                        className="aspect-4/3 w-full object-cover transition group-hover:opacity-90"
                                    />
                                    <div className="p-2">
                                        <div className="truncate text-xs font-medium">
                                            {photo.name}
                                        </div>
                                    </div>
                                </a>
                            ))}
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}

UnitShow.layout = {
    breadcrumbs: [{ title: 'Units', href: unitsIndex() }, { title: 'Unit' }],
};
