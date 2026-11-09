import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BookmarkPlus,
    Building2,
    ListFilter,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import Heading from '@/components/heading';
import { HomeDashboardTabs } from '@/components/home-dashboard-tabs';
import { ListingTrustBadges } from '@/components/listing-trust-badges';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
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
import { useOverlayParam } from '@/hooks/use-overlay-param';
import { formatMoney } from '@/lib/format-money';
import { home as guestHome } from '@/routes/guest';
import { show as showListing } from '@/routes/guest/listings';
import {
    destroy as destroySavedSearch,
    store as storeSavedSearch,
} from '@/routes/guest/saved-searches';
import type { ListedUnit, SavedSearch } from '@/types/guest';

/**
 * Guest home: browse public units and manage saved searches, plus the
 * invitation to become a landlord. Workspaces, recent searches and leases
 * live in the sidebar and on the rentals page — not as cards here.
 */
type Props = {
    units: {
        data: ListedUnit[];
        current_page: number;
        last_page: number;
        total: number;
    };
    savedSearches: SavedSearch[];
    filters: Record<string, string | number | null>;
    available_types: string[];
    canBecomeLandlord: boolean;
};

export default function GuestHome({
    units,
    savedSearches,
    filters,
    available_types,
    canBecomeLandlord,
}: Props) {
    const filterForm = useForm({
        query: (filters.query as string) ?? '',
        min_rent: (filters.min_rent as string) ?? '',
        max_rent: (filters.max_rent as string) ?? '',
        type: (filters.type as string) ?? '',
        sort: (filters.sort as string) ?? 'newest',
    });

    const saveForm = useForm({ name: '' });
    const { query: overlayQuery, setOverlayParam } = useOverlayParam();

    const isFiltersOpen =
        overlayQuery.get('filters') !== null &&
        overlayQuery.get('filters') !== '';
    const isSaveOpen =
        overlayQuery.get('save-search') !== null &&
        overlayQuery.get('save-search') !== '';

    const refetch = (
        params: Record<string, string | number | undefined>,
        options?: { replace?: boolean },
    ) => {
        router.get(
            guestHome.url({ query: params }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: options?.replace ?? false,
                only: ['units', 'filters'],
            },
        );
    };

    const applyFilters = (event?: FormEvent) => {
        event?.preventDefault();
        refetch(
            {
                query: filterForm.data.query || undefined,
                min_rent: filterForm.data.min_rent || undefined,
                max_rent: filterForm.data.max_rent || undefined,
                type: filterForm.data.type || undefined,
                sort:
                    filterForm.data.sort !== 'newest'
                        ? filterForm.data.sort
                        : undefined,
            },
            { replace: true },
        );
    };

    const clearAllFilters = () => {
        setSearchDraft('');
        filterForm.setData({
            query: '',
            min_rent: '',
            max_rent: '',
            type: '',
            sort: 'newest',
        });
        refetch({}, { replace: true });
    };

    const applyType = (type: string) => {
        filterForm.setData('type', type);
        refetch({
            query: filterForm.data.query || undefined,
            min_rent: filterForm.data.min_rent || undefined,
            max_rent: filterForm.data.max_rent || undefined,
            type: type || undefined,
            sort:
                filterForm.data.sort !== 'newest'
                    ? filterForm.data.sort
                    : undefined,
        });
    };

    // Live search: local box for responsiveness, debounced server round-trip.
    // Never fires on mount — the server already sent the filtered result.
    const [searchDraft, setSearchDraft] = useState(
        (filters.query as string) ?? '',
    );
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        if (searchDraft === ((filters.query as string) ?? '')) {
            return;
        }

        const timer = window.setTimeout(() => {
            refetch(
                {
                    query: searchDraft || undefined,
                    min_rent: (filters.min_rent as string) || undefined,
                    max_rent: (filters.max_rent as string) || undefined,
                    type: (filters.type as string) || undefined,
                    sort:
                        (filters.sort as string) &&
                        (filters.sort as string) !== 'newest'
                            ? (filters.sort as string)
                            : undefined,
                },
                { replace: true },
            );
        }, 350);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchDraft]);

    // Keep the box and the form in step with Back/Forward, saved-search
    // re-runs, and the filters overlay clearing the query. The search box
    // adjusts during render (React's documented pattern; the useState
    // setter bails out on identical values). The form syncs in an effect
    // because useForm.setData always re-renders — doing it during render
    // would schedule another render on every pass.
    const [serverQuery, setServerQuery] = useState(
        (filters.query as string) ?? '',
    );

    if (((filters.query as string) ?? '') !== serverQuery) {
        const query = (filters.query as string) ?? '';
        setServerQuery(query);
        setSearchDraft(query);
    }

    useEffect(() => {
        filterForm.setData({
            query: (filters.query as string) ?? '',
            min_rent: (filters.min_rent as string) ?? '',
            max_rent: (filters.max_rent as string) ?? '',
            type: (filters.type as string) ?? '',
            sort: (filters.sort as string) ?? 'newest',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        filters.query,
        filters.min_rent,
        filters.max_rent,
        filters.type,
        filters.sort,
    ]);

    const activeFilterSummary = useMemo(() => {
        const parts: string[] = [];

        if (filterForm.data.min_rent) {
            parts.push(`min ${filterForm.data.min_rent}`);
        }

        if (filterForm.data.max_rent) {
            parts.push(`max ${filterForm.data.max_rent}`);
        }

        if (filterForm.data.type) {
            parts.push(filterForm.data.type);
        }

        if (filterForm.data.sort && filterForm.data.sort !== 'newest') {
            parts.push(
                filterForm.data.sort === 'cheapest'
                    ? 'cheapest first'
                    : 'priciest first',
            );
        }

        return parts.length > 0 ? parts.join(' · ') : 'No extra filters';
    }, [filterForm.data]);

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
            onSuccess: () => {
                saveForm.reset('name');
                setOverlayParam('save-search', null, { replace: true });
            },
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

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <HomeDashboardTabs />
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

                {/*
                 * One card = the discovery frame, mirroring the platform
                 * lists: search and utilities live in a header band above the
                 * results rather than in their own floating card. Type chips
                 * stay visible as one-tap quick filters (the category row);
                 * everything else lives in the filters overlay.
                 */}
                <Card className="gap-0 py-0">
                    <div className="flex flex-wrap items-center gap-2 border-b border-border-strong p-4">
                        <div className="relative min-w-56 flex-1">
                            <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={searchDraft}
                                onChange={(event) =>
                                    setSearchDraft(event.target.value)
                                }
                                placeholder="Search area, building or unit type…"
                                aria-label="Search listings"
                                // shadcn Input is bg-transparent, which leaves
                                // the field defined by a border alone. On a
                                // card surface it needs a fill + a stronger
                                // border to be readable in both schemes.
                                className="border-border-strong bg-background pr-3 pl-9"
                            />
                        </div>
                        <ActionIconButton
                            icon={<ListFilter />}
                            label="Filters"
                            color="teal"
                            onClick={() => setOverlayParam('filters', '1')}
                        />
                        <ActionIconButton
                            icon={<BookmarkPlus />}
                            label="Save this search"
                            color="accent"
                            onClick={() => setOverlayParam('save-search', '1')}
                        />
                    </div>

                    {available_types.length > 0 && (
                        <div
                            className="flex flex-wrap gap-2 px-4 pt-4"
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

                    <div className="space-y-4 p-4">
                        <p
                            className="text-sm text-muted-foreground"
                            role="status"
                            aria-live="polite"
                        >
                            {units.total === 0
                                ? 'No units to show'
                                : `${units.total} unit${units.total === 1 ? '' : 's'} for rent`}
                        </p>

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
                                                        {unit.monthly_rent !==
                                                        null
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
                    </div>
                </Card>

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

                <div>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base font-medium">
                                Saved searches
                            </CardTitle>
                            <CardDescription>
                                Re-run a search in one tap, or remove one you no
                                longer need. Your five most recent are also in
                                the sidebar.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {savedSearches.length === 0 ? (
                                <div className="space-y-2">
                                    <p className="text-sm text-muted-foreground">
                                        Nothing saved yet. Run a search above
                                        and give it a name.
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setOverlayParam('save-search', '1')
                                        }
                                        className="text-sm font-medium text-accent-text hover:text-accent-text-hover"
                                    >
                                        Save the current search
                                    </button>
                                </div>
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

            <ResponsiveOverlay
                open={isFiltersOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('filters', null, { replace: true });
                    }
                }}
                title="Filters"
                description={activeFilterSummary}
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('filters', null, { replace: true })
                        }
                        actions={[
                            {
                                label: 'Clear all',
                                icon: <X className="size-4" />,
                                onClick: clearAllFilters,
                                tone: 'secondary',
                            },
                            {
                                label: 'Show results',
                                form: 'listing-filters-form',
                                type: 'submit',
                            },
                        ]}
                    />
                }
            >
                <form
                    id="listing-filters-form"
                    onSubmit={applyFilters}
                    className="space-y-4"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
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
                        <div className="space-y-2">
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
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="sort">Sort</Label>
                        <Select
                            value={filterForm.data.sort}
                            onValueChange={(value) =>
                                filterForm.setData('sort', value)
                            }
                        >
                            <SelectTrigger id="sort" aria-label="Sort listings">
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
                    </div>
                </form>
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isSaveOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('save-search', null, {
                            replace: true,
                        });
                    }
                }}
                title="Save this search"
                description={
                    hasActiveFilters
                        ? `We will keep "${activeFilterSummary}" ready for you.`
                        : 'Name this search to re-run it in one tap.'
                }
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('save-search', null, {
                                replace: true,
                            })
                        }
                        actions={[
                            {
                                label: 'Save search',
                                form: 'save-search-form',
                                type: 'submit',
                                disabled:
                                    saveForm.processing ||
                                    saveForm.data.name.trim() === '',
                            },
                        ]}
                    />
                }
            >
                <form
                    id="save-search-form"
                    onSubmit={saveSearch}
                    className="space-y-2"
                >
                    <Label htmlFor="search_name">Name</Label>
                    <Input
                        id="search_name"
                        placeholder="e.g. Kilimani 2BR under 40k"
                        value={saveForm.data.name}
                        onChange={(e) =>
                            saveForm.setData('name', e.target.value)
                        }
                    />
                    {saveForm.errors.name && (
                        <p className="text-sm text-destructive">
                            {saveForm.errors.name}
                        </p>
                    )}
                </form>
            </ResponsiveOverlay>
        </>
    );
}
