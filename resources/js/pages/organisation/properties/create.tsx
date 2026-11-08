import { Head, Link } from '@inertiajs/react';
import PropertyAssetForm, {
    type AssetPlan,
    type AssetPropertyType,
} from '@/components/assets/property-asset-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/tenant/properties';

type Organization = {
    id: number;
    name: string;
    slug: string;
};

type Props = {
    organization: Organization;
    plan: AssetPlan;
    propertyTypes: AssetPropertyType[];
    canCreateProperty: boolean;
};

export default function PropertiesCreate({
    organization,
    plan,
    propertyTypes,
    canCreateProperty,
}: Props) {
    return (
        <>
            <Head title="Add property" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        level="h1"
                        title="Add a property"
                        description={`Add ${organization.name}'s first property and its units.`}
                    />
                    <Button asChild variant="outline">
                        <Link href={index()}>Back to properties</Link>
                    </Button>
                </div>

                <PropertyAssetForm
                    plan={plan}
                    propertyTypes={propertyTypes}
                    canCreateProperty={canCreateProperty}
                    setup={false}
                />
            </div>
        </>
    );
}

PropertiesCreate.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: index(),
        },
        {
            title: 'Add property',
        },
    ],
};
