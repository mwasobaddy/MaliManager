import { Head, Link } from '@inertiajs/react';
import LandParcelAssetForm, {
    type AssetManager,
} from '@/components/assets/land-parcel-asset-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Organization = {
    id: number;
    name: string;
    slug: string;
};

type Props = {
    organization: Organization;
    managers: AssetManager[];
    parcel: null;
    selected_manager_ids: number[];
};

export default function LandParcelCreate({
    organization,
    managers,
    selected_manager_ids,
}: Props) {
    return (
        <>
            <Head title="Add land parcel" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        level="h1"
                        variant="small"
                        title="Add a land parcel"
                        description={`Record a plot of land for ${organization.name}.`}
                    />
                    <Button asChild variant="outline">
                        <Link href="/land-parcels">Back to land parcels</Link>
                    </Button>
                </div>

                <LandParcelAssetForm
                    managers={managers}
                    selected_manager_ids={selected_manager_ids}
                    setup={false}
                />
            </div>
        </>
    );
}

LandParcelCreate.layout = {
    breadcrumbs: [
        {
            title: 'Land parcels',
        },
        {
            title: 'Add land parcel',
        },
    ],
};
