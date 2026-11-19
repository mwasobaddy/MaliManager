import { Head, Link, router, usePage } from '@inertiajs/react';
import { LayoutDashboard, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { ComboChart } from '@/components/dashboard/combo-chart';
import type { ComboPoint } from '@/components/dashboard/combo-chart';
import { StatsCards } from '@/components/dashboard/stats-cards';
import { HomeDashboardTabs } from '@/components/home-dashboard-tabs';
import { AdoptionBars } from '@/components/platform/adoption-bars';
import { AlertsPanel } from '@/components/platform/alerts-panel';
import { AttentionTable } from '@/components/platform/attention-table';
import { CohortHeatmap } from '@/components/platform/cohort-heatmap';
import { FailuresTable } from '@/components/platform/failures-table';
import { LandMap } from '@/components/platform/land-map';
import { FunnelChart } from '@/components/platform/funnel-chart';
import { KpiCard, snapshotSpark } from '@/components/platform/kpi-card';
import {
    OperationsPanels,
    formatLatency,
} from '@/components/platform/operations-panels';
import { OrganizationsSection } from '@/components/platform/organizations-section';
import {
    ChartSkeleton,
    SectionSkeleton,
} from '@/components/platform/section-skeleton';
import type {
    Adoption,
    Growth,
    Histories,
    Operations,
    OrganizationRow,
    OrganizationsPagination,
    Overview,
    Payments,
} from '@/components/platform/types';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatMoney } from '@/lib/format-money';
import { dashboard } from '@/routes';
import { home as guestHome } from '@/routes/guest';
import { remind as remindLease } from '@/routes/tenant/leases';

type AdminStats = {
    organizations_count: number;
    subscribers_count: number;
    plans_count: number;
    income_total: number;
    expenses_total: number;
    net_total: number;
    plans_breakdown: { plan: string; count: number }[];
    financials_monthly: ComboPoint[];
};

type OrgStats = {
    properties_count: number;
    units_count: number;
    leases_count: number;
    active_leases: number;
    staff_count: number;
    expenses_ytd: number;
    maintenance_open: number;
    operations_monthly: ComboPoint[];
    flags?: PredictiveFlag[];
    expiring_leases?: {
        unit: string | null;
        occupant: string | null;
        ends_at: string;
        days_left: number;
    }[];
    collection: {
        expected_mtd: string;
        collected_mtd: string;
        collection_rate: number | null;
        arrears_total: string;
        overdue_count: number;
        occupancy_rate: number | null;
        occupied: number;
        vacant: number;
        units_total: number;
        net_mtd: string;
        aging: Array<{ bucket: string; total: string; count: number }>;
        defaulters: Array<{
            lease_id: number;
            occupant: string;
            unit: string | null;
            property: string;
            property_slug: string;
            outstanding: string;
            days_overdue: number;
        }>;
        expiry: { within_30: number; within_60: number; within_90: number };
        properties: Array<{
            property: string;
            income_mtd: string;
            expenses_mtd: string;
            net_mtd: string;
        }>;
    };
    attention: Array<{ severity: 'danger' | 'warning' | 'info'; text: string }>;
    property_options: Array<{ id: number; name: string }>;
    selected_property: number | null;
    portfolio: {
        units_grid: Array<{
            property: string;
            units: Array<{
                id: number;
                name: string;
                status: string;
                overdue: boolean;
                occupant: string | null;
            }>;
        }>;
        timing: { early: number; on_time: number; late: number };
        tax: Array<{
            label: string;
            total: string;
            properties: Array<{ property: string; total: string }>;
        }>;
        pnl: {
            income_ytd: string;
            expenses_ytd: string;
            net_ytd: string;
            expenses_by_category: Array<{ category: string; total: string }>;
        };
        land: Array<{
            id: number;
            name: string;
            status: string;
            city: string | null;
            acreage: string | null;
            latitude: number | null;
            longitude: number | null;
        }>;
    };
};

type RecentLease = {
    organization?: string | null;
    property?: string | null;
    status: string;
    starts_at?: string | null;
    ends_at?: string | null;
};

type PredictiveFlag = {
    severity: string;
    message: string;
};

type PersonLeaseStats = {
    leases_count: number;
    active_leases: number;
    ended_leases: number;
    total_rent: number;
    recent: RecentLease[];
    maintenance_open?: number;
    home_monthly?: ComboPoint[];
    flags?: PredictiveFlag[];
};

type Props = {
    available_years?: number[];
    admin?: AdminStats | null;
    organization?: OrgStats | null;
    searcher?: PersonLeaseStats | null;
    occupant?: PersonLeaseStats | null;
    platform?: Overview | null;
    growth?: Growth | null;
    payments?: Payments | null;
    adoption?: Adoption | null;
    operations?: Operations | null;
    platform_histories?: Histories | null;
    platform_organizations?: OrganizationRow[] | null;
    platform_organizations_pagination?: OrganizationsPagination | null;
    defaultTab?: string;
    autoOpenPropertyPicker?: boolean;
};

const TABS = [
    {
        key: 'platform',
        label: 'Platform',
        permission: 'view advanced metrics',
    },
    {
        key: 'growth',
        label: 'Growth',
        permission: 'view advanced metrics',
    },
    {
        key: 'payments',
        label: 'Payments',
        permission: 'view advanced metrics',
        // Deferred payload: the tab shows on permission alone and
        // skeletonizes until the data lands, so ?tab=payments deep
        // links keep working instead of falling back silently.
        deferred: true,
    },
    {
        key: 'adoption',
        label: 'Adoption',
        permission: 'view advanced metrics',
    },
    {
        key: 'operations',
        label: 'Operations',
        permission: 'view advanced metrics',
    },
    {
        key: 'organization',
        label: 'Organization',
        permission: 'view organization metrics',
    },
    {
        key: 'searcher',
        label: 'My rentals',
        permission: 'view searcher metrics',
    },
    { key: 'occupant', label: 'My home', permission: 'view occupant metrics' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

/**
 * What renders when the user holds none of the dashboard metric
 * permissions: a next step instead of a dead end. Fresh owners are sent
 * back into the funnel, pure guests toward browsing or becoming a
 * landlord, and everyone else toward listings.
 */
function DashboardEmptyState() {
    const { props } = usePage();
    const canBecomeLandlord = (props.auth?.canBecomeLandlord ??
        false) as boolean;

    if (canBecomeLandlord) {
        return (
            <Empty
                icon={<LayoutDashboard className="size-4" />}
                title="Nothing here yet"
                description="Your dashboard fills in once you rent a place or list one of your own."
                action={
                    <Button asChild>
                        <Link href="/become-landlord">Become a landlord</Link>
                    </Button>
                }
                secondaryAction={
                    <Link
                        href={guestHome.url()}
                        className="text-sm font-medium text-accent-text hover:text-foreground"
                    >
                        Browse listings instead
                    </Link>
                }
            />
        );
    }

    return (
        <Empty
            icon={<LayoutDashboard className="size-4" />}
            title="Nothing here yet"
            description="You don't have access to any dashboard data. Ask your administrator, or browse what's publicly listed."
            secondaryAction={
                <Link
                    href={guestHome.url()}
                    className="text-sm font-medium text-accent-text hover:text-foreground"
                >
                    Browse listings
                </Link>
            }
        />
    );
}

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

export default function Dashboard(props: Props) {
    const page = usePage();
    const permissions = (page.props.auth?.permissions ?? []) as string[];
    const availableYears = props.available_years ?? [];

    const hasRentals = (key: TabKey): boolean => {
        if (key !== 'searcher' && key !== 'occupant') {
            return true;
        }

        const stats = props[key] as { leases_count?: number } | null;

        return (stats?.leases_count ?? 0) > 0;
    };

    const visibleTabs = TABS.filter(
        (tab) =>
            permissions.includes(tab.permission) &&
            props[tab.key] !== null &&
            (props[tab.key] !== undefined ||
                ('deferred' in tab && tab.deferred === true)) &&
            // A rentals tab with no leases on record is noise: admins hold
            // every metric permission but rarely rent anything themselves.
            hasRentals(tab.key),
    );

    const pageUrl = page.url.split('?')[0];
    const pageQuery = (() => {
        const index = page.url.indexOf('?');

        return new URLSearchParams(index === -1 ? '' : page.url.slice(index));
    })();
    const urlTab = pageQuery.get('tab') as TabKey | null;
    const urlYear = pageQuery.get('year');
    const urlMonth = pageQuery.get('month');
    const urlProperty = pageQuery.get('property');

    const initialTab =
        urlTab && visibleTabs.some((tab) => tab.key === urlTab)
            ? urlTab
            : ((props.defaultTab as TabKey) ??
              visibleTabs[0]?.key ??
              'platform');

    const [activeTab, setActiveTab] = useState<TabKey>(initialTab);
    const [filterYear, setFilterYear] = useState<string>(
        urlYear ?? String(new Date().getFullYear()),
    );
    const [filterMonth, setFilterMonth] = useState<string>(urlMonth ?? '');
    const [filterProperty, setFilterProperty] = useState<string>(
        urlProperty ?? '',
    );

    const selectTab = (key: TabKey) => {
        setActiveTab(key);

        const params: Record<string, string> = { tab: key };

        if (filterYear) {
            params.year = filterYear;
        }

        if (filterMonth) {
            params.month = filterMonth;
        }

        if (filterProperty) {
            params.property = filterProperty;
        }

        router.get(pageUrl, params, {
            preserveState: true,
            replace: true,
        });
    };

    const goOrgPage = (page: number) => {
        const params: Record<string, string> = { tab: activeTab };

        if (filterYear) {
            params.year = filterYear;
        }

        if (filterMonth) {
            params.month = filterMonth;
        }

        params.orgs_page = String(page);

        if (filterProperty) {
            params.property = filterProperty;
        }

        router.get(pageUrl, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const updateFilters = (year: string, month: string) => {
        setFilterYear(year);
        setFilterMonth(month);

        const params: Record<string, string> = { tab: activeTab };

        if (year) {
            params.year = year;
        }

        if (month) {
            params.month = month;
        }

        if (filterProperty) {
            params.property = filterProperty;
        }

        router.get(pageUrl, params, {
            preserveState: true,
            replace: true,
        });
    };

    const updatePropertyFilter = (value: string) => {
        setFilterProperty(value);

        const params: Record<string, string> = { tab: activeTab };

        if (filterYear) {
            params.year = filterYear;
        }

        if (filterMonth) {
            params.month = filterMonth;
        }

        if (value) {
            params.property = value;
        }

        router.get(pageUrl, params, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <h1 className="sr-only">Dashboard</h1>
                <HomeDashboardTabs />
                {visibleTabs.length === 0 ? (
                    <Card>
                        <CardContent>
                            <DashboardEmptyState />
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="flex flex-wrap items-center gap-3">
                            <Tabs
                                value={activeTab}
                                onValueChange={(value) =>
                                    selectTab(value as TabKey)
                                }
                            >
                                <TabsList>
                                    {visibleTabs.map((tab) => (
                                        <TabsTrigger
                                            key={tab.key}
                                            value={tab.key}
                                        >
                                            {tab.label}
                                        </TabsTrigger>
                                    ))}
                                </TabsList>
                            </Tabs>

                            <div className="ml-auto flex items-center gap-2">
                                <Select
                                    value={filterYear}
                                    onValueChange={(value) =>
                                        updateFilters(value, filterMonth)
                                    }
                                >
                                    <SelectTrigger
                                        className="min-w-[100px]"
                                        aria-label="Filter by year"
                                    >
                                        <SelectValue placeholder="All years" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableYears.map((year) => (
                                            <SelectItem
                                                key={year}
                                                value={String(year)}
                                            >
                                                {year}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <Select
                                    value={filterMonth}
                                    onValueChange={(value) =>
                                        updateFilters(
                                            filterYear,
                                            value === 'all' ? '' : value,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        className="min-w-[128px]"
                                        aria-label="Filter by month"
                                    >
                                        <SelectValue placeholder="All months" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">
                                            All months
                                        </SelectItem>
                                        {MONTHS.map((name, index) => (
                                            <SelectItem
                                                key={index}
                                                value={String(index + 1)}
                                            >
                                                {name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {activeTab === 'platform' &&
                            props.platform &&
                            props.admin && (
                                <PlatformTab
                                    overview={props.platform}
                                    finances={props.admin}
                                    histories={props.platform_histories ?? null}
                                    organizations={
                                        props.platform_organizations ?? []
                                    }
                                    pagination={
                                        props.platform_organizations_pagination ??
                                        null
                                    }
                                    onOrgPage={goOrgPage}
                                />
                            )}
                        {activeTab === 'growth' && props.growth && (
                            <GrowthTab
                                growth={props.growth}
                                histories={props.platform_histories ?? null}
                            />
                        )}
                        {activeTab === 'payments' && (
                            <PaymentsTab
                                payments={props.payments ?? null}
                                currency={props.platform?.currency ?? 'KES'}
                            />
                        )}
                        {activeTab === 'adoption' && props.adoption && (
                            <AdoptionTab
                                adoption={props.adoption}
                                histories={props.platform_histories ?? null}
                                currency={props.platform?.currency ?? 'KES'}
                            />
                        )}
                        {activeTab === 'operations' && props.operations && (
                            <OperationsPanels operations={props.operations} />
                        )}
                        {activeTab === 'organization' && props.organization && (
                            <OrganizationTab
                                stats={props.organization}
                                onProperty={updatePropertyFilter}
                            />
                        )}
                        {activeTab === 'searcher' && props.searcher && (
                            <PersonTab
                                stats={props.searcher}
                                label="My rental history"
                            />
                        )}
                        {activeTab === 'occupant' && props.occupant && (
                            <PersonTab stats={props.occupant} label="My home" />
                        )}
                        {!visibleTabs.some((tab) => tab.key === 'platform') &&
                            permissions.includes('organization.manage') &&
                            props.platform_organizations &&
                            props.platform_organizations_pagination && (
                                <OrganizationsSection
                                    organizations={props.platform_organizations}
                                    pagination={
                                        props.platform_organizations_pagination
                                    }
                                    onOrgPage={goOrgPage}
                                />
                            )}
                    </>
                )}
            </div>
        </>
    );
}

function PlatformTab({
    overview,
    finances,
    histories,
    organizations,
    pagination,
    onOrgPage,
}: {
    overview: Overview;
    finances: AdminStats;
    histories: Histories | null;
    organizations: OrganizationRow[];
    pagination: OrganizationsPagination | null;
    onOrgPage: (page: number) => void;
}) {
    const { kpis, currency } = overview;
    const signupDelta =
        kpis.signups_this_month.value - kpis.signups_this_month.last_month;
    const collectedDelta =
        kpis.collected_this_month.value - kpis.collected_this_month.last_month;

    return (
        <div className="flex flex-col gap-4">
            <StatsCards
                label="Platform finances"
                items={[
                    {
                        label: 'Subscription income',
                        value: formatMoney(finances.income_total),
                        variant: 'primary',
                    },
                    {
                        label: 'Platform expenses',
                        value: formatMoney(finances.expenses_total),
                        variant: 'accent',
                    },
                    {
                        label: 'Net',
                        value: formatMoney(finances.net_total),
                        variant: 'primary',
                    },
                ]}
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <KpiCard
                    label="MRR"
                    value={formatMoney(kpis.mrr.value, currency)}
                    sub={`${kpis.mrr.paying_organizations} paying organizations`}
                    spark={snapshotSpark(
                        histories?.mrr.map((row) => Number(row.mrr)),
                    )}
                />
                <KpiCard
                    label="Active organizations"
                    value={String(kpis.active_organizations.value)}
                    sub={`${kpis.active_organizations.on_trial} on trial`}
                    spark={snapshotSpark(histories?.active)}
                />
                <KpiCard
                    label="New signups this month"
                    value={String(kpis.signups_this_month.value)}
                    sub={
                        signupDelta === 0
                            ? `same as last month (${kpis.signups_this_month.last_month})`
                            : `${signupDelta > 0 ? '+' : ''}${signupDelta} vs last month (${kpis.signups_this_month.last_month})`
                    }
                />
                <KpiCard
                    label="Past-due subscriptions"
                    value={String(kpis.past_due.value)}
                    sub={`${formatMoney(kpis.past_due.exposure, currency)} exposed`}
                    alert={kpis.past_due.value > 0}
                    spark={snapshotSpark(histories?.past_due)}
                />
                <KpiCard
                    label="Collected this month"
                    value={formatMoney(
                        kpis.collected_this_month.value,
                        currency,
                    )}
                    sub={`${collectedDelta >= 0 ? '+' : ''}${formatMoney(collectedDelta, currency)} vs last month`}
                    spark={kpis.collected_this_month.spark}
                />
                <KpiCard
                    label="Trials ending soon"
                    value={String(kpis.trials_ending.value)}
                    sub={`within ${kpis.trials_ending.within_days} days`}
                    spark={snapshotSpark(histories?.trials)}
                />
            </div>

            <div className="grid items-start gap-4 xl:grid-cols-5">
                <div className="xl:col-span-3">
                    <ComboChart
                        title="Subscription income — trailing 12 months"
                        data={overview.income_trend}
                        series={[
                            {
                                key: 'income',
                                label: 'Income',
                                color: 'var(--chart-1)',
                                type: 'bar',
                            },
                        ]}
                    />
                </div>
                <div className="xl:col-span-2">
                    <AlertsPanel alerts={overview.alerts} />
                </div>
            </div>

            <AttentionTable rows={overview.attention} />

            <OrganizationsSection
                organizations={organizations}
                pagination={pagination}
                onOrgPage={onOrgPage}
            />
        </div>
    );
}

function GrowthTab({
    growth,
    histories,
}: {
    growth: Growth;
    histories: Histories | null;
}) {
    return (
        <div className="flex flex-col gap-4">
            <div className="grid items-start gap-4 xl:grid-cols-2">
                <FunnelChart stages={growth.funnel} />
                <CohortHeatmap cohorts={growth.cohorts} />
            </div>
            {histories === null || histories.as_of === null ? (
                <Card>
                    <CardContent className="py-8 text-center text-sm text-muted-foreground">
                        MRR history starts with the first nightly snapshot — run{' '}
                        <code className="rounded bg-muted px-1 py-0.5 text-xs">
                            metrics:snapshot
                        </code>{' '}
                        or wait for tonight&apos;s run.
                    </CardContent>
                </Card>
            ) : (
                <ComboChart
                    title={`MRR history (snapshots since ${histories.as_of})`}
                    data={histories.mrr}
                    series={[
                        {
                            key: 'mrr',
                            label: 'MRR',
                            color: 'var(--chart-1)',
                            type: 'area',
                        },
                    ]}
                />
            )}
        </div>
    );
}

function PaymentsTab({
    payments,
    currency,
}: {
    payments: Payments | null | undefined;
    currency: string;
}) {
    if (payments == null) {
        return (
            <>
                <ChartSkeleton />
                <SectionSkeleton blocks={4} />
            </>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <ComboChart
                title="Gateway attempts and success rate — trailing 30 days"
                data={payments.daily.map((day) => ({
                    label: day.label,
                    attempts: day.attempts,
                    ...(day.success_rate !== null
                        ? { success_rate: day.success_rate }
                        : {}),
                }))}
                series={[
                    {
                        key: 'attempts',
                        label: 'Attempts',
                        color: 'var(--chart-1)',
                        type: 'bar',
                    },
                    {
                        key: 'success_rate',
                        label: 'Success %',
                        color: 'var(--chart-2)',
                        type: 'line',
                        axis: 'right',
                    },
                ]}
            />
            <p className="text-sm text-muted-foreground">
                Median confirmation latency:{' '}
                {formatLatency(payments.latency_this_week)} trailing 7 days vs{' '}
                {formatLatency(payments.latency_last_week)} the week before.
                Pending attempts are still in flight and never count toward the
                rate.
            </p>
            <FailuresTable failures={payments.failures} currency={currency} />
        </div>
    );
}

function AdoptionTab({
    adoption,
    histories,
    currency,
}: {
    adoption: Adoption;
    histories: Histories | null;
    currency: string;
}) {
    return (
        <div className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    label="Properties under management"
                    value={String(adoption.properties_total)}
                    sub="across active organizations"
                />
                <KpiCard
                    label="Units under management"
                    value={String(adoption.units_total)}
                    sub="across active organizations"
                />
                <KpiCard
                    label="Rent collected this month"
                    value={formatMoney(adoption.rent_month_to_date, currency)}
                    sub="received payments platform-wide"
                />
                <KpiCard
                    label="Open maintenance"
                    value={String(adoption.maintenance_open)}
                    sub="platform-wide right now"
                />
            </div>
            <AdoptionBars features={adoption.features} />
            {(histories?.rent.length ?? 0) > 0 && (
                <ComboChart
                    title="Rent collected trend (nightly snapshots)"
                    data={histories?.rent ?? []}
                    series={[
                        {
                            key: 'rent',
                            label: 'Rent',
                            color: 'var(--chart-2)',
                            type: 'bar',
                        },
                    ]}
                />
            )}
        </div>
    );
}

/**
 * Collected vs expected hero: the one card that answers "am I getting
 * paid". A progress bar, not a number — position in the month matters as
 * much as the total.
 */
function RentCollectionHero({
    collection,
}: {
    collection: OrgStats['collection'];
}) {
    const expected = Number(collection.expected_mtd);
    const collected = Number(collection.collected_mtd);
    const pct = expected > 0 ? Math.min((collected / expected) * 100, 100) : 0;

    return (
        <Card>
            <CardContent className="flex flex-col gap-2 pt-5">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <p className="text-sm text-muted-foreground">
                        Rent collected this month
                    </p>
                    <p className="text-sm text-muted-foreground tabular-nums">
                        {collection.collection_rate === null
                            ? 'no rent due yet'
                            : `${collection.collection_rate}% of expected`}
                    </p>
                </div>
                <p className="text-2xl font-semibold tracking-tight tabular-nums">
                    {formatMoney(collection.collected_mtd)} of{' '}
                    {formatMoney(collection.expected_mtd)}
                </p>
                <div
                    className="h-2.5 overflow-hidden rounded-full bg-muted"
                    role="img"
                    aria-label={`Collected ${formatMoney(collection.collected_mtd)} of ${formatMoney(collection.expected_mtd)} expected`}
                >
                    <div
                        className="h-full rounded-full bg-brand-primary"
                        style={{ width: `${pct}%` }}
                    />
                </div>
                <p className="text-xs text-muted-foreground tabular-nums">
                    {formatMoney(collection.arrears_total)} overdue across{' '}
                    {collection.overdue_count}{' '}
                    {collection.overdue_count === 1 ? 'lease' : 'leases'}
                </p>
            </CardContent>
        </Card>
    );
}

function ArrearsAgingCard({
    aging,
}: {
    aging: OrgStats['collection']['aging'];
}) {
    const max = Math.max(...aging.map((row) => Number(row.total)), 1);

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Arrears aging
                </CardTitle>
                <CardDescription>
                    Overdue shillings by how late they are.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {aging.map((row) => (
                    <div key={row.bucket} className="flex flex-col gap-1">
                        <div className="flex items-baseline justify-between gap-2 text-sm">
                            <span className="font-medium">{row.bucket}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {formatMoney(row.total)} · {row.count}
                            </span>
                        </div>
                        <div
                            className="h-2.5 overflow-hidden rounded-full bg-muted"
                            role="img"
                            aria-label={`${row.bucket}: ${formatMoney(row.total)} across ${row.count} leases`}
                        >
                            <div
                                className="h-full rounded-full bg-chart-2"
                                style={{
                                    width: `${(Number(row.total) / max) * 100}%`,
                                }}
                            />
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function DefaulterTable({
    defaulters,
}: {
    defaulters: OrgStats['collection']['defaulters'];
}) {
    const permissions = (usePage().props.auth?.permissions ?? []) as string[];
    const canRemind = permissions.includes('lease.manage');

    if (defaulters.length === 0) {
        return (
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-base font-medium">
                        Top defaulters
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        Nobody owes rent right now.
                    </p>
                </CardContent>
            </Card>
        );
    }

    const remind = (propertySlug: string, leaseId: number) => {
        router.post(
            remindLease({ property: propertySlug, lease: leaseId }).url,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Top defaulters
                </CardTitle>
                <CardDescription>
                    Largest balances first — remind with one tap.
                </CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto px-0">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-y text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <th className="px-4 py-2 font-medium">Tenant</th>
                            <th className="px-4 py-2 font-medium">Owes</th>
                            <th className="px-4 py-2 font-medium">Late by</th>
                            <th className="px-4 py-2 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {defaulters.map((row) => (
                            <tr
                                key={row.lease_id}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-2.5">
                                    <p className="font-medium">
                                        {row.occupant === ''
                                            ? 'Unnamed tenant'
                                            : row.occupant}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {row.unit ?? '—'} · {row.property}
                                    </p>
                                </td>
                                <td className="px-4 py-2.5 font-medium tabular-nums">
                                    {formatMoney(row.outstanding)}
                                </td>
                                <td className="px-4 py-2.5 text-muted-foreground tabular-nums">
                                    {row.days_overdue}d
                                </td>
                                <td className="px-4 py-2.5">
                                    <div className="flex justify-end">
                                        {canRemind && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    remind(
                                                        row.property_slug,
                                                        row.lease_id,
                                                    )
                                                }
                                            >
                                                Remind
                                            </Button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}

function PropertyPerformanceCard({
    properties,
}: {
    properties: OrgStats['collection']['properties'];
}) {
    if (properties.length === 0) {
        return null;
    }

    const max = Math.max(...properties.map((row) => Number(row.income_mtd)), 1);

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Income vs expenses by property
                </CardTitle>
                <CardDescription>
                    This month, received rent against booked expenses.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {properties.map((row) => (
                    <div key={row.property} className="flex flex-col gap-1">
                        <div className="flex items-baseline justify-between gap-2 text-sm">
                            <span className="font-medium">{row.property}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {formatMoney(row.income_mtd)} in ·{' '}
                                {formatMoney(row.expenses_mtd)} out · net{' '}
                                {formatMoney(row.net_mtd)}
                            </span>
                        </div>
                        <div
                            className="h-2.5 overflow-hidden rounded-full bg-muted"
                            role="img"
                            aria-label={`${row.property}: ${formatMoney(row.income_mtd)} income, ${formatMoney(row.expenses_mtd)} expenses`}
                        >
                            <div
                                className="h-full rounded-full bg-chart-1"
                                style={{
                                    width: `${(Number(row.income_mtd) / max) * 100}%`,
                                }}
                            />
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function OrganizationTab({
    stats,
    onProperty,
}: {
    stats: OrgStats;
    onProperty: (value: string) => void;
}) {
    return (
        <div className="flex flex-col gap-4">
            {stats.property_options.length > 1 && (
                <div className="flex items-center gap-2">
                    <span className="text-sm text-muted-foreground">
                        Property
                    </span>
                    <Select
                        value={
                            stats.selected_property === null
                                ? 'all'
                                : String(stats.selected_property)
                        }
                        onValueChange={(value) =>
                            onProperty(value === 'all' ? '' : value)
                        }
                    >
                        <SelectTrigger
                            className="min-w-[180px]"
                            aria-label="Filter widgets by property"
                        >
                            <SelectValue placeholder="All properties" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All properties</SelectItem>
                            {stats.property_options.map((option) => (
                                <SelectItem
                                    key={option.id}
                                    value={String(option.id)}
                                >
                                    {option.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {stats.selected_property !== null && (
                        <span className="text-xs text-muted-foreground">
                            Every widget below is scoped to this property; land
                            parcels always show the full portfolio.
                        </span>
                    )}
                </div>
            )}
            <StatsCards
                label="Organization overview"
                items={[
                    {
                        label: 'Total Properties',
                        value: stats.properties_count,
                        variant: 'accent',
                    },
                    {
                        label: 'Total Units',
                        value: stats.units_count,
                        variant: 'accent',
                    },
                    {
                        label: 'Total Staff',
                        value: stats.staff_count,
                        variant: 'primary',
                    },
                    {
                        label: 'Expenses YTD',
                        value: formatMoney(stats.expenses_ytd),
                        variant: 'primary',
                    },
                ]}
            />

            <RentCollectionHero collection={stats.collection} />

            {stats.attention.length > 0 && (
                <AlertsPanel alerts={stats.attention} />
            )}

            <div className="grid items-start gap-4 xl:grid-cols-2">
                <ArrearsAgingCard aging={stats.collection.aging} />
                <DefaulterTable defaulters={stats.collection.defaulters} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    label="Occupancy rate"
                    value={
                        stats.collection.occupancy_rate === null
                            ? '—'
                            : `${stats.collection.occupancy_rate}%`
                    }
                    sub={`${stats.collection.occupied} of ${stats.collection.units_total} units`}
                />
                <KpiCard
                    label="Vacant units"
                    value={String(stats.collection.vacant)}
                    sub="ready to fill"
                />
                <KpiCard
                    label="Leases expiring ≤ 30 days"
                    value={String(stats.collection.expiry.within_30)}
                    sub={`30–60: ${stats.collection.expiry.within_60} · 60–90: ${stats.collection.expiry.within_90}`}
                />
                <KpiCard
                    label="Net this month"
                    value={formatMoney(stats.collection.net_mtd)}
                    sub="collected minus property expenses"
                />
            </div>

            <PropertyPerformanceCard properties={stats.collection.properties} />

            <ComboChart
                title="Operations"
                data={stats.operations_monthly}
                series={[
                    {
                        key: 'expenses',
                        label: 'Expenses',
                        color: 'var(--chart-2)',
                        type: 'area',
                        axis: 'left',
                    },
                    {
                        key: 'maintenance',
                        label: 'Maintenance requests',
                        color: 'var(--chart-3)',
                        type: 'bar',
                        axis: 'right',
                    },
                ]}
            />

            {(stats.expiring_leases?.length ?? 0) > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base font-medium">
                            Leases expiring soon
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-2 text-sm">
                            {stats.expiring_leases!.map((lease) => (
                                <li
                                    key={`${lease.unit}-${lease.ends_at}`}
                                    className="flex items-center justify-between gap-2"
                                >
                                    <span>
                                        {lease.occupant} — {lease.unit}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {lease.ends_at} · {lease.days_left}d
                                        left
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            )}

            {stats.flags && stats.flags.length > 0 && (
                <Card className="border-status-warning/40">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base font-medium">
                            <TriangleAlert
                                className="size-4 text-status-warning"
                                aria-hidden
                            />
                            Needs attention
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-1.5 text-sm">
                            {stats.flags.map((flag, index) => (
                                <li
                                    key={index}
                                    className="flex items-start gap-2"
                                >
                                    <span
                                        className={`mt-1 inline-block size-2 shrink-0 rounded-full ${
                                            flag.severity === 'high'
                                                ? 'bg-status-danger'
                                                : 'bg-status-warning'
                                        }`}
                                    />
                                    {flag.message}
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            )}

            <UnitGridCard grid={stats.portfolio.units_grid} />

            <TimingCard timing={stats.portfolio.timing} />

            <div className="grid items-start gap-4 xl:grid-cols-2">
                <TaxCard tax={stats.portfolio.tax} />
                <PnlCard pnl={stats.portfolio.pnl} />
            </div>

            <LandMap parcels={stats.portfolio.land} />
        </div>
    );
}

function UnitGridCard({ grid }: { grid: OrgStats['portfolio']['units_grid'] }) {
    const fill: Record<string, string> = {
        occupied: 'bg-status-success-bg text-status-success-fg',
        vacant: 'bg-status-neutral-bg text-status-neutral-fg',
        maintenance: 'bg-status-warning-bg text-status-warning-fg',
    };

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Unit map
                </CardTitle>
                <CardDescription>
                    Every unit at a glance — the red ring marks arrears.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {grid.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No units in scope.
                    </p>
                )}
                {grid.map((building) => (
                    <div key={building.property}>
                        <p className="mb-2 text-sm font-medium">
                            {building.property}
                        </p>
                        <div className="grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-8">
                            {building.units.map((unit) => (
                                <div
                                    key={unit.id}
                                    title={`${unit.name} · ${unit.status}${unit.occupant ? ` · ${unit.occupant}` : ''}`}
                                    className={`rounded-lg border px-2 py-2 text-center ${fill[unit.status] ?? 'bg-muted text-muted-foreground'} ${unit.overdue ? 'ring-2 ring-status-danger' : ''}`}
                                >
                                    <p className="truncate text-xs font-semibold">
                                        {unit.name}
                                    </p>
                                    <p className="truncate text-[10px] opacity-80">
                                        {unit.occupant ?? unit.status}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function TimingCard({ timing }: { timing: OrgStats['portfolio']['timing'] }) {
    const total = timing.early + timing.on_time + timing.late;
    const rows = [
        { label: 'Paid early', count: timing.early, fill: 'bg-chart-1' },
        {
            label: 'On time (within 7 days)',
            count: timing.on_time,
            fill: 'bg-chart-2',
        },
        { label: 'Late', count: timing.late, fill: 'bg-status-danger' },
    ];

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Payment timing
                </CardTitle>
                <CardDescription>
                    Received payments against their billing period — early,
                    within a 7-day grace window, or late.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {total === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No dated payments yet.
                    </p>
                )}
                {rows.map((row) => (
                    <div key={row.label} className="flex flex-col gap-1">
                        <div className="flex items-baseline justify-between gap-2 text-sm">
                            <span className="font-medium">{row.label}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {row.count}
                                {total > 0 &&
                                    ` · ${Math.round((row.count / total) * 100)}%`}
                            </span>
                        </div>
                        <div
                            className="h-2.5 overflow-hidden rounded-full bg-muted"
                            role="img"
                            aria-label={`${row.label}: ${row.count} payments`}
                        >
                            <div
                                className={`h-full rounded-full ${row.fill}`}
                                style={{
                                    width: `${total > 0 ? Math.max((row.count / total) * 100, row.count > 0 ? 2 : 0) : 0}%`,
                                }}
                            />
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function TaxCard({ tax }: { tax: OrgStats['portfolio']['tax'] }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Rental income summary
                </CardTitle>
                <CardDescription>
                    Gross received rent per month — for the accountant, not tax
                    advice. Confirm treatment with one before filing.
                </CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto px-0">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-y text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <th className="px-4 py-2 font-medium">Month</th>
                            <th className="px-4 py-2 text-right font-medium">
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {tax.map((row) => (
                            <tr
                                key={row.label}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-2 font-medium">
                                    {row.label}
                                </td>
                                <td className="px-4 py-2 text-right tabular-nums">
                                    {formatMoney(row.total)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}

function PnlCard({ pnl }: { pnl: OrgStats['portfolio']['pnl'] }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Profit and loss
                </CardTitle>
                <CardDescription>
                    Year to date, property-attributed only — org overhead
                    without a property stays out.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-2 text-sm">
                <div className="flex items-baseline justify-between gap-2">
                    <span className="font-medium">Rental income</span>
                    <span className="font-semibold tabular-nums">
                        {formatMoney(pnl.income_ytd)}
                    </span>
                </div>
                {pnl.expenses_by_category.map((row) => (
                    <div
                        key={row.category}
                        className="flex items-baseline justify-between gap-2 text-muted-foreground"
                    >
                        <span>{row.category}</span>
                        <span className="tabular-nums">
                            {formatMoney(row.total)}
                        </span>
                    </div>
                ))}
                <div className="flex items-baseline justify-between gap-2 border-t pt-2">
                    <span className="text-muted-foreground">
                        Expenses total
                    </span>
                    <span className="tabular-nums">
                        {formatMoney(pnl.expenses_ytd)}
                    </span>
                </div>
                <div className="flex items-baseline justify-between gap-2">
                    <span className="font-medium">Net</span>
                    <span className="font-semibold tabular-nums">
                        {formatMoney(pnl.net_ytd)}
                    </span>
                </div>
                <div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => window.print()}
                    >
                        Print
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function PersonTab({
    stats,
    label,
}: {
    stats: PersonLeaseStats;
    label: string;
}) {
    return (
        <div className="flex flex-col gap-4">
            <StatsCards
                label={label}
                items={[
                    {
                        label: 'Total Leases',
                        value: stats.leases_count,
                        variant: 'accent',
                    },
                    {
                        label: 'Active Leases',
                        value: stats.active_leases,
                        variant: 'accent',
                    },
                    {
                        label: 'Total Rent',
                        value: formatMoney(stats.total_rent),
                        variant: 'primary',
                    },
                    {
                        label: 'Open Maintenance',
                        value: stats.maintenance_open ?? 0,
                        variant: 'primary',
                    },
                ]}
            />

            {stats.home_monthly && (
                <ComboChart
                    title="Your home"
                    data={stats.home_monthly}
                    series={[
                        {
                            key: 'rent',
                            label: 'Rent',
                            color: 'var(--chart-1)',
                            type: 'area',
                            axis: 'left',
                        },
                        {
                            key: 'maintenance',
                            label: 'Maintenance',
                            color: 'var(--chart-3)',
                            type: 'bar',
                            axis: 'right',
                        },
                    ]}
                />
            )}

            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0">
                    <CardTitle className="text-base font-medium">
                        Recent {label.toLowerCase()}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {stats.recent.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No lease history yet.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="px-3 py-2 font-medium">
                                            Organization
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Property
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Start
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            End
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {stats.recent.map((lease, index) => (
                                        <tr key={index} className="border-b">
                                            <td className="px-3 py-2">
                                                {lease.organization ?? '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {lease.property ?? '—'}
                                            </td>
                                            <td className="px-3 py-2 capitalize">
                                                {lease.status}
                                            </td>
                                            <td className="px-3 py-2">
                                                {lease.starts_at ?? '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {lease.ends_at ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
