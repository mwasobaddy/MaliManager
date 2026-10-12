import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, store } from '@/routes/organizations';

type Plan = { id: number; name: string; slug: string };
type OwnerOption = { id: number; name: string; email: string };

type Props = {
    plans: Plan[];
    owners: OwnerOption[];
};

export default function OrganizationsCreate({ plans, owners }: Props) {
    const [ownerMode, setOwnerMode] = useState<'existing' | 'new'>('existing');

    return (
        <>
            <Head title="New organization" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Create organization"
                    description="Provisions the tenant, domain, default sub-roles and owner membership."
                />

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Organization</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...store.form()}
                            className="grid gap-4 sm:grid-cols-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="owner_mode"
                                        value={ownerMode}
                                    />
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input id="name" name="name" required />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="phone">Phone</Label>
                                        <Input id="phone" name="phone" />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="plan_id">Plan</Label>
                                        <Select
    name="plan_id"
    defaultValue={String(plans[0]?.id ?? '')}
>
    <SelectTrigger
        id="plan_id"
    >
        <SelectValue />
    </SelectTrigger>
    <SelectContent>
        {plans.map((plan) => (
            <SelectItem
                value={String(plan.id)}
            >
                {plan.name}
            </SelectItem>
        ))}
    </SelectContent>
</Select>
                                        <InputError message={errors.plan_id} />
                                    </div>

                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label>Owner</Label>
                                        <div className="flex gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant={
                                                    ownerMode === 'existing'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                onClick={() =>
                                                    setOwnerMode('existing')
                                                }
                                            >
                                                Existing user
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant={
                                                    ownerMode === 'new'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                onClick={() =>
                                                    setOwnerMode('new')
                                                }
                                            >
                                                Create new person &amp; user
                                            </Button>
                                        </div>
                                    </div>

                                    {ownerMode === 'existing' ? (
                                        <div className="grid gap-2 sm:col-span-2">
                                            <Label htmlFor="owner_user_id">
                                                Owner user
                                            </Label>
                                            <Select
    name="owner_user_id"
    defaultValue={String(owners[0]?.id ?? '')}
>
    <SelectTrigger
        id="owner_user_id"
    >
        <SelectValue />
    </SelectTrigger>
    <SelectContent>
        {owners.map((owner) => (
            <SelectItem key={owner.id} value={String(owner.id)}>
                {owner.name} ({owner.email})
            </SelectItem>
        ))}
    </SelectContent>
</Select>
                                            <InputError
                                                message={errors.owner_user_id}
                                            />
                                        </div>
                                    ) : (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="owner_first_name">
                                                    Owner first name
                                                </Label>
                                                <Input
                                                    id="owner_first_name"
                                                    name="owner_first_name"
                                                    required={
                                                        ownerMode === 'new'
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.owner_first_name
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="owner_last_name">
                                                    Owner last name
                                                </Label>
                                                <Input
                                                    id="owner_last_name"
                                                    name="owner_last_name"
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="owner_email">
                                                    Owner email
                                                </Label>
                                                <Input
                                                    id="owner_email"
                                                    name="owner_email"
                                                    type="email"
                                                    required={
                                                        ownerMode === 'new'
                                                    }
                                                />
                                                <InputError
                                                    message={errors.owner_email}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="owner_phone">
                                                    Owner phone
                                                </Label>
                                                <Input
                                                    id="owner_phone"
                                                    name="owner_phone"
                                                />
                                            </div>
                                        </>
                                    )}

                                    <div className="flex gap-2 sm:col-span-2">
                                        <Button
                                            type="button"
                                            variant="shadcndefault"
                                            asChild
                                        >
                                            <Link href={index()}>Cancel</Link>
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Create organization
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

OrganizationsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Organizations', href: '/organizations' },
        { title: 'New', href: '/organizations/create' },
    ],
};
