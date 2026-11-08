import { Head, Link, router, useForm } from '@inertiajs/react';
import { BookmarkPlus, Building2, Search, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { ListingTrustBadges } from '@/components/listing-trust-badges';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatMoney } from '@/lib/format-money';
import { home as guestHome } from '@/routes/guest';
import { show as showListing } from '@/routes/guest/listings';
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
    available_types: string[];
    canBecomeLandlord: boolean;
    workspaces: Workspace[];
};

export default function GuestHome({
    units,
    leases,
    savedSearches,
    filters,
    available_types,
    canBecomeLandlord,
    workspaces,
}: Props) {
    const filterForm = useForm({
        query: (filters.query as string) ?? '',
        min_rent: (filters.min_rent as string) ?? '',
        max_rent: (filters.max_rent as string) ?? '',
        type: (filters.type as string) ?? '',
        sort: (filters.sort as string) ?? 'newest',
    });

    const saveForm = useForm({ name: '' });

    const applyFilters = (event?: FormEvent) => {
        event?.preventDefault();
        router.get(
            guestHome.url({
                query: {
                    query: filterForm.data.query || undefined,
                    min_rent: filterForm.data.min_rent || undefined,
                    max_rent: filterForm.data.max_rent || undefined,
                    type: filterForm.data.type || undefined,
                    sort:
                        filterForm.data.sort !== 'newest'
                            ? filterForm.data.sort
                            : undefined,
                },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const applyType = (type: string) => {
        filterForm.setData('type', type);
        router.get(
            guestHome.url({
                query: {
                    query: filterForm.data.query || undefined,
                    min_rent: filterForm.data.min_rent || undefined,
                    max_rent: filterForm.data.max_rent || undefined,
                    type: type || undefined,
                    sort:
                        filterForm.data.sort !== 'newest'
                            ? filterForm.data.sort
                            : undefined,
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

    const hasActiveFilters = Boolean(
        filters.query ?? filters.min_rent ?? filters.max_rent ?? filters.type,
    );

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
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <a href={workspace.url}>Open</a>
                                            </Button>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardContent className="pt-6">
                        {available_types.length > 0 && (
                            <div
                                className="mb-4 flex flex-wrap gap-2"
                                role="group"
                                aria-label="Filter by unit type"
                            >
                                {['', ...available_types].map((type) => (
                                    <Button
                                        key={type || 'all'}
                                        type="button"
                                        variant={
                                            filterForm.data.type === type
                                                ? 'default'
                                                : 'outline'
                                        }
                                        size="sm"
                                        onClick={() => applyType(type)}
                                    >
                                        {type === '' ? 'All types' : type}
                                    </Button>
                                ))}
                            </div>
                        )}
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
                            className="mt-3 flex flex-wrap items-end gap-3 border-t border-border pt-3"
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
                                        saveForm.setData('name', e.target.value)
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

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p
                        className="text-sm text-muted-foreground"
                        role="status"
                        aria-live="polite"
                    >
                        {units.total === 0
                            ? 'No units to show'
                            : `${units.total} unit${units.total === 1 ? '' : 's'} for rent`}
                    </p>
                    {units.total > 1 && (
                        <Select
                            value={filterForm.data.sort}
                            onValueChange={(value) => {
                                filterForm.setData('sort', value);
                                router.get(
                                    guestHome.url({
                                        query: {
                                            query:
                                                filterForm.data.query ||
                                                undefined,
                                            min_rent:
                                                filterForm.data.min_rent ||
                                                undefined,
                                            max_rent:
                                                filterForm.data.max_rent ||
                                                undefined,
                                            type:
                                                filterForm.data.type ||
                                                undefined,
                                            sort:
                                                value !== 'newest'
                                                    ? value
                                                    : undefined,
                                        },
                                    }),
                                    {},
                                    {
                                        preserveScroll: true,
                                        preserveState: true,
                                    },
                                );
                            }}
                        >
                            <SelectTrigger
                                className="w-[140px]"
                                aria-label="Sort listings"
                            >
                                <SelectValue placeholder="Sort" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="newest">
                                    Newest first
                                </SelectItem>
                                <SelectItem value="cheapest">
                                    Cheapest first
                                </SelectItem>
                                <SelectItem value="priciest">
                                    Priciest first
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    )}
                </div>

                {units.data.length === 0 ? (
                    <Empty
                        icon={<Search className="size-4" />}
                        title={
                            hasActiveFilters
                                ? 'No units match that search'
                                : 'No public listings yet'
                        }
                        description={
                            hasActiveFilters
                                ? 'Try widening the rent range or clearing the search.'
                                : 'New listings appear here as landlords publish them. Save a search above and we will keep it ready for when they land.'
                        }
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {units.data.map((unit) => (
                            <Link
                                key={unit.id}
                                href={showListing(unit.id).url}
                                className="rounded-xl"
                            >
                                <Card className="h-full overflow-hidden pt-0 transition-colors hover:bg-muted">
                                    {unit.photo_url ? (
                                        <img
                                            src={unit.photo_url}
                                            alt={`${unit.name} photo`}
                                            className="aspect-video w-full object-cover"
                                            loading="lazy"
                                        />
                                    ) : (
                                        <div
                                            aria-hidden
                                            className="flex aspect-video w-full items-center justify-center bg-muted"
                                        >
                                            <Building2 className="size-8 text-muted-foreground" />
                                        </div>
                                    )}
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
                                    <CardContent className="flex flex-col gap-3">
                                        <div className="flex items-center justify-between">
                                            <span className="font-semibold tabular-nums">
                                                {unit.monthly_rent !== null
                                                    ? formatMoney(
                                                          unit.monthly_rent,
                                                          unit.currency,
                                                      )
                                                    : '—'}
                                            </span>
                                            {unit.type && (
                                                <Badge variant="neutral">
                                                    {unit.type}
                                                </Badge>
                                            )}
                                        </div>
                                        <ListingTrustBadges
                                            trust={unit.trust}
                                        />
                                    </CardContent>
                                </Card>
                            </Link>
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
                                <p className="text-sm text-muted-foreground">
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
                                                onClick={() => runSaved(search)}
                                                className="text-sm font-medium text-accent-text hover:text-accent-text-hover"
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
                                                        destroySavedSearch({
                                                            search: search.id,
                                                        }).url,
                                                        {
                                                            preserveScroll: true,
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
                                <p className="text-sm text-muted-foreground">
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

                {canBecomeLandlord && (
                    <Card>
                        <CardContent className="flex flex-wrap items-center justify-between gap-4 py-6">
                            <div className="flex items-center gap-3">
                                <span className="flex size-10 items-center justify-center rounded-lg bg-muted">
                                    <Building2
                                        className="size-5 text-muted-foreground"
                                        aria-hidden
                                    />
                                </span>
                                <div>
                                    <p className="font-medium">
                                        Have a property to let?
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        List it free and collect rent through
                                        M-Pesa.
                                    </p>
                                </div>
                            </div>
                            <Button asChild>
                                <Link href="/become-landlord">
                                    Become a landlord
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
