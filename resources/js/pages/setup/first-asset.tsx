import { Head, Link } from '@inertiajs/react';
import { Building2, Map } from 'lucide-react';
import Heading from '@/components/heading';
import {
    SetupSidePanel,
    SetupStepsTopCard,
} from '@/components/setup-side-panel';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { create as createParcel } from '@/routes/tenant/land-parcels';
import { create as createProperty } from '@/routes/tenant/properties';

type Organization = {
    id: number;
    name: string;
} | null;

type Props = {
    organization: Organization;
};

export default function FirstAsset({ organization }: Props) {
    return (
        <>
            <Head title="Add your first asset" />

            <div className="mx-auto grid w-full max-w-7xl flex-1 grid-cols-7 gap-8 px-4 py-8">
                <SetupSidePanel
                    intro={[
                        'Your organization is ready',
                        'Add your first building or land parcel',
                    ]}
                    current={2}
                />

                <div className="col-span-7 space-y-6 lg:col-span-4">
                    <SetupStepsTopCard current={2} />

                    <Heading
                        variant="small"
                        level="h1"
                        title={`Add your first asset${organization ? ` to ${organization.name}` : ''}`}
                        description="Choose what you'd like to set up first. You can add the other type later."
                    />

                    <div className="grid gap-4">
                        <Link
                            href={createProperty({ query: { setup: 1 } })}
                            className="rounded-xl border border-input bg-card transition-colors hover:bg-muted"
                        >
                            <Card className="border-0 bg-transparent shadow-none">
                                <CardHeader>
                                    <div className="flex items-center gap-2">
                                        <Building2 className="size-5 text-muted-foreground" />
                                        <CardTitle>Building</CardTitle>
                                    </div>
                                    <CardDescription>
                                        A property with units, occupants and
                                        leases — apartments, offices, etc.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Button asChild>
                                        <span>Add a building</span>
                                    </Button>
                                </CardContent>
                            </Card>
                        </Link>

                        <Link
                            href={createParcel({ query: { setup: 1 } })}
                            className="rounded-xl border border-input bg-card transition-colors hover:bg-muted"
                        >
                            <Card className="border-0 bg-transparent shadow-none">
                                <CardHeader>
                                    <div className="flex items-center gap-2">
                                        <Map className="size-5 text-muted-foreground" />
                                        <CardTitle>Land parcel</CardTitle>
                                    </div>
                                    <CardDescription>
                                        A standalone plot of land with zoning,
                                        acreage and lease availability.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Button asChild>
                                        <span>Add a land parcel</span>
                                    </Button>
                                </CardContent>
                            </Card>
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
