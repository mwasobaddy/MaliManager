import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Cell,
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { TriangleAlert } from 'lucide-react';
import { ChartCard  } from '@/components/dashboard/chart-card';
import type {SeriesPoint} from '@/components/dashboard/chart-card';
import { PeriodSelector  } from '@/components/dashboard/period-selector';
import type {Period} from '@/components/dashboard/period-selector';
import { SampleBadge } from '@/components/dashboard/sample-badge';
import { StatCard } from '@/components/dashboard/stat-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

type AdminStats = {
    organizations_count: number;
    subscribers_count: number;
    plans_count: number;
    plans_breakdown: { plan: string; count: number }[];
    expenses: SeriesPoint[];
    subscriptions_weekly: SeriesPoint[];
    subscriptions_monthly: SeriesPoint[];
    subscriptions_yearly: SeriesPoint[];
};

type OrgStats = {
    properties_count: number;
    units_count: number;
    leases_count: number;
    active_leases: number;
    expenses: SeriesPoint[];
    maintenance_open: number;
    maintenance_series: SeriesPoint[];
    flags?: PredictiveFlag[];
    expiring_leases?: {
        unit: string | null;
        occupant: string | null;
        ends_at: string;
        days_left: number;
    }[];
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
    rent_trend?: SeriesPoint[];
    flags?: PredictiveFlag[];
};

type Props = {
    admin?: AdminStats | null;
    organization?: OrgStats | null;
    searcher?: PersonLeaseStats | null;
    occupant?: PersonLeaseStats | null;
    defaultTab?: string;
    autoOpenPropertyPicker?: boolean;
};

const PIE_COLORS = ['#2563eb', '#16a34a', '#f59e0b', '#db2777', '#0891b2', '#7c3aed'];

const TABS = [
    { key: 'admin', label: 'Advanced metrics', permission: 'view advanced metrics' },
    { key: 'organization', label: 'Organization', permission: 'view organization metrics' },
    { key: 'searcher', label: 'My rentals', permission: 'view searcher metrics' },
    { key: 'occupant', label: 'My home', permission: 'view occupant metrics' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

export default function Dashboard(props: Props) {
    const page = usePage();
    const permissions = ((page.props.auth?.permissions ?? []) as string[]) ?? [];

    const visibleTabs = TABS.filter((tab) => permissions.includes(tab.permission));

    const urlTab = new URLSearchParams(window.location.search).get('tab') as TabKey | null;
    const initialTab =
        urlTab && visibleTabs.some((tab) => tab.key === urlTab)
            ? urlTab
            : ((props.defaultTab as TabKey) ?? visibleTabs[0]?.key ?? 'admin');

    const [activeTab, setActiveTab] = useState<TabKey>(initialTab);
    const [period, setPeriod] = useState<Period>('monthly');

    const selectTab = (key: TabKey) => {
        setActiveTab(key);
        router.get(
            window.location.pathname,
            { tab: key },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {visibleTabs.length === 0 ? (
                    <p className="text-muted-foreground">
                        You don't have any dashboard data yet.
                    </p>
                ) : (
                    <>
                        <div className="flex flex-wrap gap-2">
                            {visibleTabs.map((tab) => (
                                <button
                                    key={tab.key}
                                    type="button"
                                    onClick={() => selectTab(tab.key)}
                                    className={
                                        activeTab === tab.key
                                            ? 'rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground'
                                            : 'rounded-md border px-4 py-2 text-sm font-medium text-muted-foreground'
                                    }
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>

                        {activeTab === 'admin' && props.admin && (
                            <AdminTab stats={props.admin} period={period} onPeriod={setPeriod} />
                        )}
                        {activeTab === 'organization' && props.organization && (
                            <OrganizationTab stats={props.organization} />
                        )}
                        {activeTab === 'searcher' && props.searcher && (
                            <PersonTab stats={props.searcher} title="My rental history" />
                        )}
                        {activeTab === 'occupant' && props.occupant && (
                            <PersonTab stats={props.occupant} title="My home" />
                        )}
                    </>
                )}
            </div>
        </>
    );
}

function AdminTab({
    stats,
    period,
    onPeriod,
}: {
    stats: AdminStats;
    period: Period;
    onPeriod: (period: Period) => void;
}) {
    const subscriptionData = stats[`subscriptions_${period}`];

    return (
        <div className="flex flex-col gap-4">
            <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                <StatCard label="Organizations" value={stats.organizations_count} />
                <StatCard label="Subscribers" value={stats.subscribers_count} hint="Organizations on a plan" />
                <StatCard label="Plans" value={stats.plans_count} />
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <ChartCard title="Expenses" data={stats.expenses} type="area" sample color="#db2777" />
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-base font-medium">Subscriptions</CardTitle>
                        <PeriodSelector value={period} onChange={onPeriod} />
                    </CardHeader>
                    <CardContent>
                        <ResponsiveContainer width="100%" height={220}>
                            <LineChart data={subscriptionData} margin={{ left: -20, right: 10, top: 6 }}>
                                <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                <XAxis dataKey="label" tick={{ fontSize: 11 }} />
                                <YAxis tick={{ fontSize: 11 }} />
                                <Tooltip />
                                <Line type="monotone" dataKey="value" stroke="#16a34a" />
                            </LineChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base font-medium">Subscribers by plan</CardTitle>
                </CardHeader>
                <CardContent>
                    <ResponsiveContainer width="100%" height={260}>
                        <PieChart>
                            <Pie
                                data={stats.plans_breakdown}
                                dataKey="count"
                                nameKey="plan"
                                outerRadius={90}
                                label
                            >
                                {stats.plans_breakdown.map((_, index) => (
                                    <Cell key={index} fill={PIE_COLORS[index % PIE_COLORS.length]} />
                                ))}
                            </Pie>
                            <Legend />
                            <Tooltip />
                        </PieChart>
                    </ResponsiveContainer>
                </CardContent>
            </Card>
        </div>
    );
}

function OrganizationTab({ stats }: { stats: OrgStats }) {
    return (
        <div className="flex flex-col gap-4">
            <div className="grid auto-rows-min gap-4 md:grid-cols-4">
                <StatCard label="Properties" value={stats.properties_count} />
                <StatCard label="Units" value={stats.units_count} />
                <StatCard label="Active leases" value={stats.active_leases} />
                <StatCard label="Open maintenance" value={stats.maintenance_open} hint="Sample data" />
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <ChartCard title="Expenses" data={stats.expenses} type="area" sample color="#db2777" />
                <ChartCard
                    title="Maintenance requests"
                    data={stats.maintenance_series}
                    type="bar"
                    sample
                    color="#f59e0b"
                />
            </div>

            {(stats.expiring_leases?.length ?? 0) > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base font-medium">Leases expiring soon</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-2 text-sm">
                            {stats.expiring_leases!.map((lease) => (
                                <li key={`${lease.unit}-${lease.ends_at}`} className="flex items-center justify-between gap-2">
                                    <span>
                                        {lease.occupant} — {lease.unit}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {lease.ends_at} · {lease.days_left}d left
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            )}

            {stats.flags && stats.flags.length > 0 &&(
                <Card className="border-amber-300 dark:border-amber-800">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base font-medium">
                            <TriangleAlert className="size-4 text-amber-600 dark:text-amber-400" />
                            Needs attention
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-1.5 text-sm">
                            {stats.flags.map((flag, index) => (
                                <li key={index} className="flex items-start gap-2">
                                    <span
                                        className={`mt-1 inline-block size-2 shrink-0 rounded-full ${
                                            flag.severity === 'high' ? 'bg-red-500' : 'bg-amber-500'
                                        }`}
                                    />
                                    {flag.message}
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

function PersonTab({ stats, title }: { stats: PersonLeaseStats; title: string }) {
    return (
        <div className="flex flex-col gap-4">
            <div className="grid auto-rows-min gap-4 md:grid-cols-4">
                <StatCard label={title} value={stats.leases_count} hint="Total leases" />
                <StatCard label="Active" value={stats.active_leases} />
                <StatCard label="Ended" value={stats.ended_leases} />
                <StatCard label="Total rent" value={`$${Number(stats.total_rent).toLocaleString()}`} />
            </div>

            {stats.rent_trend && (
                <ChartCard title="Rent trend" data={stats.rent_trend} type="area" sample color="#0891b2" />
            )}

            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0">
                    <CardTitle className="text-base font-medium">Recent {title.toLowerCase()}</CardTitle>
                    {stats.maintenance_open !== undefined && <SampleBadge />}
                </CardHeader>
                <CardContent>
                    {stats.recent.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No lease history yet.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="px-3 py-2 font-medium">Organization</th>
                                        <th className="px-3 py-2 font-medium">Property</th>
                                        <th className="px-3 py-2 font-medium">Status</th>
                                        <th className="px-3 py-2 font-medium">Start</th>
                                        <th className="px-3 py-2 font-medium">End</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {stats.recent.map((lease, index) => (
                                        <tr key={index} className="border-b">
                                            <td className="px-3 py-2">{lease.organization ?? '—'}</td>
                                            <td className="px-3 py-2">{lease.property ?? '—'}</td>
                                            <td className="px-3 py-2 capitalize">{lease.status}</td>
                                            <td className="px-3 py-2">{lease.starts_at ?? '—'}</td>
                                            <td className="px-3 py-2">{lease.ends_at ?? '—'}</td>
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
