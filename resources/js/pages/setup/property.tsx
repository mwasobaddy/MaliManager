import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import PropertyAssetForm, {
    type AssetPlan,
    type AssetPropertyType,
} from '@/components/assets/property-asset-form';
import Heading from '@/components/heading';
import {
    SetupSidePanel,
    SetupStepsTopCard,
} from '@/components/setup-side-panel';
import type { SetupProgress } from '@/components/setup-steps';
import { Button } from '@/components/ui/button';
import { firstAsset } from '@/routes/onboarding';

type Props = {
    organization: {
        id: number;
        name: string;
        slug: string;
    };
    plan: AssetPlan;
    propertyTypes: AssetPropertyType[];
    canCreateProperty: boolean;
    setupProgress: SetupProgress;
};

/**
 * Step 2 (building branch) of the landlord funnel. Renders inside
 * `SetupLayout` — same shell as first-asset/team/review — so adding the
 * first building never drops out of the funnel into the app sidebar.
 */
export default function SetupProperty({
    organization,
    plan,
    propertyTypes,
    canCreateProperty,
    setupProgress,
}: Props) {
    return (
        <>
            <Head title="Add your first building" />

            <div className="mx-auto grid w-full max-w-7xl flex-1 grid-cols-7 gap-8 px-4 py-8">
                <SetupSidePanel
                    intro={[
                        'Your organization is ready',
                        'Add your first building or land parcel',
                    ]}
                    current={2}
                    progress={setupProgress}
                />

                <div className="col-span-7 space-y-6 lg:col-span-4">
                    <SetupStepsTopCard current={2} progress={setupProgress} />

                    <div className="flex items-start justify-between gap-4">
                        <Heading
                            level="h1"
                            title="Add your first building"
                            description={`Add ${organization.name}'s first property and its units. You can add a land parcel later.`}
                        />
                        <Button asChild variant="outline" size="sm">
                            <Link href={firstAsset.url()}>
                                <ArrowLeft className="size-4" />
                                Back to choices
                            </Link>
                        </Button>
                    </div>

                    <PropertyAssetForm
                        plan={plan}
                        propertyTypes={propertyTypes}
                        canCreateProperty={canCreateProperty}
                        setup
                    />
                </div>
            </div>
        </>
    );
}
