import { Head, Link, router, useForm } from '@inertiajs/react';
import { BookmarkPlus, Building2, Search, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { home as guestHome } from '@/routes/guest';
import {
    destroy as destroySavedSearch,
    store as storeSavedSearch,
} from '@/routes/guest/saved-searches';
import type {
    GuestLease,
    ListedUnit,
    SavedSearch,
    Workspace,
} from '@/types/guest';

/**
 * Guest home: browse public units, re-run saved searches, see own leases, and
 * become a landlord. The central-domain landing for anyone without a property
 * context.
 */
type Props = {
    units: {
        data: ListedUnit[];
        current_page: number;
        last_page: number;
        total: number;
    };
    leases: GuestLease[];
    savedSearches: SavedSearch[];
    filters: Record<string, string | number | null>;
    canBecomeLandlord: boolean;
    workspaces: Workspace[];
};

export default function GuestHome({
    units,
    leases,
    savedSearches,
    filters,
    canBecomeLandlord,
    workspaces,
}: Props) {
    const filterForm = useForm({
        query: (filters.query as string) ?? '',
        min_rent: (filters.min_rent as string) ?? '',
        max_rent: (filters.max_rent as string) ?? '',
        type: (filters.type as string) ?? '',
    });

    const saveForm = useForm({ name: '' });

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            guestHome.url({
                query: {
                    query: filterForm.data.query || undefined,
                    min_rent: filterForm.data.min_rent || undefined,
                    max_rent: filterForm.data.max_rent || undefined,
                    type: filterForm.data.type || undefined,
                },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const saveSearch = (event: FormEvent) => {
        event.preventDefault();
        saveForm.transform((data) => ({
            ...data,
            filters: {
                query: filterForm.data.query || null,
                min_rent: filterForm.data.min_rent || null,
                max_rent: filterForm.data.max_rent || null,
                type: filterForm.data.type || null,
            },
        }));
        saveForm.post(storeSavedSearch().url, {
            preserveScroll: true,
            onSuccess: () => saveForm.reset('name'),
        });
    };

    const runSaved = (search: SavedSearch) => {
        router.get(
            guestHome.url({ query: search.filters }),
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Find a place" />

            <div className="mx-auto max-w-5xl space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        level="h1"
                        title="Find a place to rent"
                        description="Browse units listed by landlords on MaliManager."
                    />
                    {canBecomeLandlord && (
                        <Button asChild>
                            <Link href="/become-landlord">
                                <Building2 className="size-4" />
                                Become a landlord
                            </Link>
                        </Button>
                    )}
                </div>

                <Card>
                    <CardContent className="pt-6">
                        <form
                            onSubmit={applyFilters}
                            className="flex flex-wrap items-end gap-3"
                        >
                            <div className="min-w-48 flex-1 space-y-2">
                                <Label htmlFor="query">Search</Label>
                                <Input
                                    id="query"
                                    placeholder="Area, building or unit type…"
                                    value={filterForm.data.query}
                                    onChange={(e) =>
                                        filterForm.setData(
                                            'query',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="w-32 space-y-2">
                                <Label htmlFor="min_rent">Min rent</Label>
                                <Input
                                    id="min_rent"
                                    type="number"
                                    min="0"
                                    value={filterForm.data.min_rent}
                                    onChange={(e) =>
                                        filterForm.setData(
                                            'min_rent',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="w-32 space-y-2">
                                <Label htmlFor="max_rent">Max rent</Label>
                                <Input
                                    id="max_rent"
                                    type="number"
                                    min="0"
                                    value={filterForm.data.max_rent}
                                    onChange={(e) =>
                                        filterForm.setData(
                                            'max_rent',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <Button type="submit">
                                <Search className="size-4" />
                                Search
                            </Button>
                        </form>

                        <form
                            onSubmit={saveSearch}
                            className="mt-3 flex flex-wrap items-end gap-3"
                        >
                            <div className="min-w-48 flex-1 space-y-2">
                                <Label htmlFor="search_name">
                                    Save this search
                                </Label>
                                <Input
                                    id="search_name"
                                    placeholder="e.g. Kilimani 2BR under 40k"
                                    value={saveForm.data.name}
                                    onChange={(e) =>
                                        saveForm.setData(
                                            'name',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                variant="accent"
                                disabled={
                                    saveForm.processing ||
                                    saveForm.data.name.trim() === ''
                                }
                            >
                                <BookmarkPlus className="size-4" />
                                Save
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {units.data.length === 0 ? (
                    <Empty
                        icon={<Search className="size-4" />}
                        title="No units found"
                        description="Try widening the rent range or clearing the search. New listings appear here as landlords publish them."
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {units.data.map((unit) => (
                            <Card key={unit.id}>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {unit.name}
                                    </CardTitle>
                                    <CardDescription>
                                        {unit.property_name ?? '—'}
                                        {unit.organization_name
                                            ? ` · ${unit.organization_name}`
                                            : ''}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex items-center justify-between">
                                    <span className="font-semibold tabular-nums">
                                        {unit.monthly_rent !== null
                                            ? `${Number(unit.monthly_rent).toLocaleString()}`
                                            : '—'}
                                    </span>
                                    {unit.type && (
                                        <Badge variant="neutral">
                                            {unit.type}
                                        </Badge>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {units.last_page > 1 && (
                    <Pagination
                        currentPage={units.current_page}
                        lastPage={units.last_page}
                        total={units.total}
                        label="units"
                        onChange={(page) =>
                            router.get(
                                guestHome.url({
                                    query: { ...filters, page },
                                }),
                                {},
                                { preserveScroll: true, preserveState: true },
                            )
                        }
                    />
                )}

                {workspaces.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base font-medium">
                                Your workspaces
                            </CardTitle>
                            <CardDescription>
                                Organizations you own or work for.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-2">
                                {workspaces.map((workspace) => (
                                    <li
                                        key={workspace.organization_id}
                                        className="flex items-center justify-between gap-2 text-sm"
                                    >
                                        <span>
                                            {workspace.organization_name}
                                        </span>
                                        <span className="flex items-center gap-2">
                                            <Badge
                                                variant={
                                                    workspace.role === 'owner'
                                                        ? 'primary'
                                                        : 'neutral'
                                                }
                                            >
                                                {workspace.role}
                                            </Badge>
                                            <Button asChild variant="ghost" size="sm">
                                                <a href={workspace.url}>
                                                    Open
                                                </a>
                                            </Button>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base font-medium">
                                Saved searches
                            </CardTitle>
                            <CardDescription>
                                Re-run a search in one tap.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {savedSearches.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    Nothing saved yet. Run a search above and
                                    give it a name.
                                </p>
                            ) : (
                                <ul className="space-y-2">
                                    {savedSearches.map((search) => (
                                        <li
                                            key={search.id}
                                            className="flex items-center justify-between gap-2"
                                        >
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    runSaved(search)
                                                }
                                                className="text-accent-text hover:text-accent-text-hover text-sm font-medium"
                                            >
                                                {search.name}
                                            </button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Delete ${search.name}`}
                                                onClick={() =>
                                                    router.delete(
                                                        destroySavedSearch({ search: search.id }).url,
                                                        {
                                                            preserveScroll:
                                                                true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base font-medium">
                                My leases
                            </CardTitle>
                            <CardDescription>
                                Units you currently or previously rented.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {leases.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No leases yet. When you rent a unit listed
                                    here, it shows up in this list.
                                </p>
                            ) : (
                                <ul className="space-y-2">
                                    {leases.map((lease) => (
                                        <li
                                            key={lease.id}
                                            className="flex items-center justify-between gap-2 text-sm"
                                        >
                                            <span>
                                                {lease.unit_name ??
                                                    lease.property_name ??
                                                    `Lease #${lease.id}`}
                                            </span>
                                            <Badge
                                                variant={
                                                    lease.status === 'active'
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            >
                                                {lease.status}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
