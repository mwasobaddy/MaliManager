import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Building2, Users } from 'lucide-react';
import Heading from '@/components/heading';
import {
    SetupSidePanel,
    SetupStepsTopCard,
} from '@/components/setup-side-panel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { overview as tenantOverview } from '@/routes/tenant';

type PropertySummary = {
    id: number;
    name: string;
    slug: string;
    units_count: number;
};

type TeamMember = {
    name: string;
    email: string;
    role: string;
};

type Props = {
    organization: {
        id: number;
        name: string;
        slug: string;
        plan_name: string | null;
        currency: string;
    };
    properties: PropertySummary[];
    team: TeamMember[];
};

export default function SetupReview({ organization, properties, team }: Props) {
    return (
        <>
            <Head title="Review and launch" />

            <div className="mx-auto grid w-full max-w-7xl flex-1 grid-cols-7 gap-8 px-4 py-8">
                <SetupSidePanel
                    intro={[
                        'Everything is in place',
                        'Review your setup, then open your dashboard.',
                    ]}
                    current={4}
                />

                <div className="col-span-7 space-y-6 lg:col-span-4">
                    <SetupStepsTopCard current={4} />

                    <Heading
                        level="h1"
                        title="Review and launch"
                        description="Confirm everything looks right, then open your dashboard."
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base font-medium">
                                Organization
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-muted-foreground">
                                    Name
                                </span>
                                <span className="font-medium">
                                    {organization.name}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-muted-foreground">
                                    Plan
                                </span>
                                <span className="font-medium">
                                    {organization.plan_name ?? '—'}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-muted-foreground">
                                    Currency
                                </span>
                                <span className="font-medium tabular-nums">
                                    {organization.currency}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base font-medium">
                                Properties
                            </CardTitle>
                            <CardDescription>
                                {properties.length === 0
                                    ? 'No properties yet — add your first one anytime from the dashboard.'
                                    : `${properties.length} ${properties.length === 1 ? 'property' : 'properties'} ready to manage.`}
                            </CardDescription>
                        </CardHeader>
                        {properties.length > 0 && (
                            <CardContent>
                                <ul className="space-y-2">
                                    {properties.map((property) => (
                                        <li
                                            key={property.id}
                                            className="flex items-center justify-between gap-2 text-sm"
                                        >
                                            <span className="flex items-center gap-2">
                                                <Building2 className="size-4 text-muted-foreground" />
                                                <span className="font-medium">
                                                    {property.name}
                                                </span>
                                            </span>
                                            <Badge variant="neutral">
                                                {property.units_count}{' '}
                                                {property.units_count === 1
                                                    ? 'unit'
                                                    : 'units'}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        )}
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base font-medium">
                                Team
                            </CardTitle>
                            <CardDescription>
                                {team.length <= 1
                                    ? 'Just you so far — invite people any time from the Staff page.'
                                    : `${team.length} people can access this organization.`}
                            </CardDescription>
                        </CardHeader>
                        {team.length > 0 && (
                            <CardContent>
                                <ul className="space-y-2">
                                    {team.map((member) => (
                                        <li
                                            key={member.email}
                                            className="flex items-center justify-between gap-2 text-sm"
                                        >
                                            <span className="flex items-center gap-2">
                                                <Users className="size-4 text-muted-foreground" />
                                                <span className="font-medium">
                                                    {member.name}
                                                </span>
                                            </span>
                                            <Badge
                                                variant={
                                                    member.role === 'owner'
                                                        ? 'primary'
                                                        : 'neutral'
                                                }
                                            >
                                                {member.role}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        )}
                    </Card>

                    <Button asChild size="lg">
                        <Link href={tenantOverview().url}>
                            Open your dashboard
                            <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
