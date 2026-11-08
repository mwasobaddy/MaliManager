import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    SetupSidePanel,
    SetupStepsTopCard,
} from '@/components/setup-side-panel';
import type { SetupProgress } from '@/components/setup-steps';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { review as setupReview } from '@/routes/tenant/setup';
import { store as storeTeam } from '@/routes/tenant/setup/team';
import { firstAsset } from '@/routes/onboarding';

type SubRole = {
    id: number;
    name: string;
};

type Props = {
    organization: { id: number; name: string; slug: string };
    sub_roles: SubRole[];
    setupProgress: SetupProgress;
};

export default function SetupTeam({
    organization,
    sub_roles,
    setupProgress,
}: Props) {
    const [subRoleId, setSubRoleId] = useState('');

    return (
        <>
            <Head title="Invite your team" />

            <div className="mx-auto grid w-full max-w-7xl flex-1 grid-cols-7 gap-8 px-4 py-8">
                <SetupSidePanel
                    intro={[
                        'Bring your people with you',
                        'Caretakers and managers get their own logins.',
                    ]}
                    current={3}
                    progress={setupProgress}
                />

                <div className="col-span-7 space-y-6 lg:col-span-4">
                    <SetupStepsTopCard current={3} progress={setupProgress} />

                    <div className="flex items-start justify-between gap-4">
                        <Heading
                            level="h1"
                            title="Invite your team"
                            description={`Give ${organization.name} someone to help run it — or skip this step and do it later.`}
                        />
                        <Button asChild variant="outline" size="sm">
                            <Link href={firstAsset.url()}>
                                <ArrowLeft className="size-4" />
                                Back
                            </Link>
                        </Button>
                    </div>

                    <Form {...storeTeam.form()} className="space-y-6">
                        {({ processing, errors }) => (
                            <>
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base font-medium">
                                            Team member
                                        </CardTitle>
                                        <CardDescription>
                                            They will receive their own login.
                                            You can assign properties later.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">
                                                Full name
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                type="text"
                                                required
                                                autoComplete="off"
                                                placeholder="Jane Wanjiru"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="email">
                                                Email address
                                            </Label>
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                required
                                                autoComplete="off"
                                                placeholder="jane@example.com"
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="phone">
                                                Phone (optional)
                                            </Label>
                                            <Input
                                                id="phone"
                                                name="phone"
                                                type="tel"
                                                autoComplete="off"
                                                placeholder="+254 700 000 000"
                                            />
                                            <InputError
                                                message={errors.phone}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="sub_role_id">
                                                Role
                                            </Label>
                                            <input
                                                type="hidden"
                                                name="sub_role_id"
                                                value={subRoleId}
                                            />
                                            <Select
                                                value={subRoleId}
                                                onValueChange={setSubRoleId}
                                            >
                                                <SelectTrigger
                                                    id="sub_role_id"
                                                    className="w-full"
                                                    aria-label="Team member role"
                                                >
                                                    <SelectValue placeholder="Select a role" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {sub_roles.map((role) => (
                                                        <SelectItem
                                                            key={role.id}
                                                            value={String(
                                                                role.id,
                                                            )}
                                                        >
                                                            {role.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={errors.sub_role_id}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                <div className="flex flex-wrap items-center gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Inviting…'
                                            : 'Invite team member'}
                                    </Button>
                                    <Button asChild variant="ghost">
                                        <Link href={setupReview.url()}>
                                            Skip for now
                                        </Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
