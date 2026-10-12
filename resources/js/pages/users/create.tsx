import { Form, Head, Link } from '@inertiajs/react';
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
import { index, store } from '@/routes/users';

type PlatformRole = { value: string; label: string };

type Props = {
    platformRoles: PlatformRole[];
};

export default function UsersCreate({ platformRoles }: Props) {
    return (
        <>
            <Head title="New user" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Create user"
                    description="Creates the person record and their user account."
                />

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Person &amp; account</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...store.form()}
                            className="grid gap-4 sm:grid-cols-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="first_name">
                                            First name
                                        </Label>
                                        <Input
                                            id="first_name"
                                            name="first_name"
                                            required
                                        />
                                        <InputError
                                            message={errors.first_name}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="last_name">
                                            Last name
                                        </Label>
                                        <Input
                                            id="last_name"
                                            name="last_name"
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="phone">Phone</Label>
                                        <Input id="phone" name="phone" />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="national_id">
                                            National ID
                                        </Label>
                                        <Input
                                            id="national_id"
                                            name="national_id"
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="gender">Gender</Label>
                                        <Select name="gender">
                                            <SelectTrigger id="gender">
                                                <SelectValue placeholder="Unspecified" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="male">
                                                    Male
                                                </SelectItem>
                                                <SelectItem value="female">
                                                    Female
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="status">Status</Label>
                                        <Select
    name="status"
    defaultValue="active"
>
    <SelectTrigger
        id="status"
    >
        <SelectValue />
    </SelectTrigger>
    <SelectContent>
        <SelectItem
            value={"active"}
        >
            Active
        </SelectItem>
        <SelectItem
            value={"inactive"}
        >
            Inactive
        </SelectItem>
        <SelectItem
            value={"suspended"}
        >
            Suspended
        </SelectItem>
    </SelectContent>
</Select>
                                        <InputError message={errors.status} />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label>Platform roles</Label>
                                        <div className="flex flex-wrap gap-3">
                                            {platformRoles.map((role) => (
                                                <label
                                                    key={role.value}
                                                    className="flex items-center gap-2 text-sm"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        name="roles[]"
                                                        value={role.value}
                                                    />
                                                    {role.label}
                                                </label>
                                            ))}
                                        </div>
                                    </div>
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
                                            Create user
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

UsersCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Users', href: '/users' },
        { title: 'New user', href: '/users/create' },
    ],
};
