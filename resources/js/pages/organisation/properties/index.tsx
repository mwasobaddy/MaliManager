import { Head, Link, router, usePage, Form } from '@inertiajs/react';
import { Building2, Plus } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { LabelledIconButton } from '@/components/labelled-icon-button';
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
import { store as storeBranch } from '@/routes/tenant/branches';
import { create, dashboard } from '@/routes/tenant/properties';

type Organization = {
    id: number;
    name: string;
    slug: string;
};

type Branch = {
    id: number;
    name: string;
};

type Property = {
    id: number;
    name: string;
    slug: string;
    city: string | null;
    status: string;
    units_count: number;
    branch: { id: number; name: string } | null;
};

type Props = {
    organization: Organization;
    is_owner: boolean;
    properties: Property[];
    branches: Branch[];
    filters: { branch: number | null };
    canCreateProperty: boolean;
};

export default function PropertiesIndex({
    organization,
    is_owner,
    properties,
    branches,
    filters,
    canCreateProperty,
}: Props) {
    const pageUrl = usePage().url.split('?')[0];
    const [branchFormOpen, setBranchFormOpen] = useState(false);
    const [branchName, setBranchName] = useState('');

    function filterByBranch(value: string) {
        router.get(
            pageUrl,
            { branch: value === 'all' ? '' : value },
            { preserveState: true, only: ['properties', 'filters'] },
        );
    }

    return (
        <>
            <Head title="Your properties" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        className="min-w-0 flex-1"
                        level="h1"
                        variant="small"
                        title={`${organization.name} properties`}
                        description="Select a property to manage its units, or add a new one."
                    />
                    {canCreateProperty && (
                        <LabelledIconButton
                            icon={<Plus />}
                            label="Add property"
                            tooltip="Add a property to this organization"
                            color="primary"
                            href={create().url}
                            className="ml-auto max-w-20 sm:max-w-24"
                        />
                    )}
                </div>

                {branches.length > 1 || is_owner ? (
                    <div className="flex flex-wrap items-center gap-2">
                        {branches.length > 1 ? (
                            <Select
                                value={
                                    filters.branch
                                        ? String(filters.branch)
                                        : 'all'
                                }
                                onValueChange={filterByBranch}
                            >
                                <SelectTrigger
                                    aria-label="Filter by branch"
                                    className="w-48"
                                >
                                    <SelectValue placeholder="All branches" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All branches
                                    </SelectItem>
                                    {branches.map((branch) => (
                                        <SelectItem
                                            key={branch.id}
                                            value={String(branch.id)}
                                        >
                                            {branch.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : null}
                        {is_owner ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setBranchFormOpen((open) => !open)
                                }
                            >
                                <Plus className="size-4" />
                                New branch
                            </Button>
                        ) : null}
                    </div>
                ) : null}
                {is_owner && branchFormOpen ? (
                    <Form
                        {...storeBranch.form()}
                        onSuccess={() => {
                            setBranchFormOpen(false);
                            setBranchName('');
                        }}
                        className="flex flex-wrap items-end gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid min-w-56 flex-1 gap-2">
                                    <Label htmlFor="branch-name">
                                        Branch name
                                    </Label>
                                    <Input
                                        id="branch-name"
                                        name="name"
                                        type="text"
                                        required
                                        autoComplete="off"
                                        placeholder="Westlands"
                                        value={branchName}
                                        onChange={(event) =>
                                            setBranchName(event.target.value)
                                        }
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <Button type="submit" disabled={processing}>
                                    Add branch
                                </Button>
                            </>
                        )}
                    </Form>
                ) : null}

                {properties.length === 0 ? (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <Building2 className="mx-auto size-10 text-muted-foreground" />
                            <p className="mt-4 font-medium">
                                No properties yet
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Add your first property to start managing units.
                            </p>
                            {canCreateProperty && (
                                <Button asChild className="mt-6">
                                    <Link href={create()}>
                                        Add your first property
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {properties.map((property) => (
                            <Link
                                key={property.id}
                                href={dashboard(property.slug)}
                                className="rounded-xl border border-input bg-card transition-colors hover:bg-muted"
                            >
                                <Card className="border-0 bg-transparent shadow-none">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Building2 className="size-4 text-muted-foreground" />
                                            <CardTitle>
                                                {property.name}
                                            </CardTitle>
                                        </div>
                                        <CardDescription>
                                            {property.city ?? 'No city set'}
                                            {property.branch
                                                ? ` · ${property.branch.name}`
                                                : null}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="text-sm text-muted-foreground">
                                        {property.units_count}{' '}
                                        {property.units_count === 1
                                            ? 'unit'
                                            : 'units'}
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

PropertiesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
        },
    ],
};
