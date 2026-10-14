import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Building2, ImageOff } from 'lucide-react';
import Heading from '@/components/heading';
import { Card } from '@/components/ui/card';
import { index as unitsIndex } from '@/routes/tenant/units';

type Unit = {
    id: number;
    name: string;
    slug: string;
    type: string | null;
    status: string;
    monthly_rent: string | null;
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
        ['Monthly rent', unit.monthly_rent ?? '—'],
        ['Deposit', unit.deposit ?? '—'],
    ];

    return (
        <>
            <Head title={`Unit ${unit.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <Heading
                            title={`Unit ${unit.name}`}
                            description="Photos and letting details for this unit."
                        />
                        {unit.property.slug && (
                            <p className="text-muted-foreground mt-1 flex items-center gap-1.5 text-sm">
                                <Building2 className="size-4 shrink-0" />
                                {unit.property.name}
                            </p>
                        )}
                    </div>
                    <Link
                        href={unitsIndex()}
                        className="text-muted-foreground hover:text-foreground flex items-center gap-1.5 text-sm"
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
                        <p className="text-muted-foreground mt-1 text-xs">
                            Stored at
                            {' '}
                            <code>
                                {'{organization}/{property}/{unit}/photos'}
                            </code>
                            .
                        </p>
                    </div>

                    {unit.photos.length === 0 ? (
                        <div className="text-muted-foreground flex items-center gap-2 p-8 text-sm">
                            <ImageOff className="size-4 shrink-0" />
                            No photos yet. Add them when creating the unit.
                        </div>
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
    breadcrumbs: [{ title: 'Units' }, { title: 'Unit' }],
};