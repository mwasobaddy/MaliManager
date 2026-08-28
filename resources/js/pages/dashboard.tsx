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
import {
    Activity,
    Archive,
    Building2,
    CreditCard,
    FileText,
    Home,
    KeyRound,
    TrendingUp,
    Users,
    Wallet,
    Wrench,
} from 'lucide-react';
import { StatsCards } from '@/components/dashboard/stats-cards';
import { ComboChart } from '@/components/dashboard/combo-chart';
import type { ComboPoint } from '@/components/dashboard/combo-chart';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

type AdminStats = {
    organizations_count: number;
    subscribers_count: number;
    plans_count: number;
    expenses_ytd: number;
    new_subscribers: number;
    plans_breakdown: { plan: string; count: number }[];
    financials_monthly: ComboPoint[];
};

type OrgStats = {
    properties_count: number;
    units_count: number;
    leases_count: number;
    active_leases: number;
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
                        <Tabs value={activeTab} onValueChange={(value) => selectTab(value as TabKey)}>
                            <TabsList>
                                {visibleTabs.map((tab) => (
                                    <TabsTrigger key={tab.key} value={tab.key}>
                                        {tab.label}
                                    </TabsTrigger>
                                ))}
                            </TabsList>
                        </Tabs>

                        {activeTab === 'admin' && props.admin && (
                            <AdminTab stats={props.admin} />
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

function AdminTab({ stats }: { stats: AdminStats }) {
    return (
        <div className="flex flex-col gap-4">
            <StatsCards
                label="Platform overview"
                items={[
                    {
                        title: 'Organizations',
                        value: stats.organizations_count,
                        description: 'Total organizations',
                        icon: <Building2 />,
                    },
                    {
                        title: 'Subscribers',
                        value: stats.subscribers_count,
                        description: 'Organizations on a plan',
                        icon: <Users />,
                    },
                    {
                        title: 'Plans',
                        value: stats.plans_count,
                        description: 'Available plans',
                        icon: <CreditCard />,
                    },
                    {
                        title: 'Expenses YTD',
                        value: `$${Number(stats.expenses_ytd).toLocaleString()}`,
                        description: 'Across all organizations',
                        icon: <Wallet />,
                    },
                    {
                        title: 'New subscribers',
                        value: stats.new_subscribers,
                        description: 'Sample data',
                        icon: <TrendingUp />,
                    },
                ]}
            />

            <ComboChart
                title="Financials"
                data={stats.financials_monthly}
                series={[
                    { key: 'expenses', label: 'Expenses', color: '#db2777', type: 'area', axis: 'left' },
                    {
                        key: 'subscriptions',
                        label: 'New subscribers',
                        color: '#16a34a',
                        type: 'line',
                        axis: 'right',
                        sample: true,
                    },
                ]}
            />

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
            <StatsCards
                label="Organization overview"
                items={[
                    {
                        title: 'Properties',
                        value: stats.properties_count,
                        description: 'Total properties',
                        icon: <Building2 />,
                    },
                    {
                        title: 'Units',
                        value: stats.units_count,
                        description: 'Total units',
                        icon: <Home />,
                    },
                    {
                        title: 'Active leases',
                        value: stats.active_leases,
                        description: 'Currently active',
                        icon: <KeyRound />,
                    },
                    {
                        title: 'Open maintenance',
                        value: stats.maintenance_open,
                        description: 'Currently open',
                        icon: <Wrench />,
                    },
                    {
                        title: 'Expenses YTD',
                        value: `$${Number(stats.expenses_ytd).toLocaleString()}`,
                        description: 'Across managed assets',
                        icon: <Wallet />,
                    },
                ]}
            />

            <ComboChart
                title="Operations"
                data={stats.operations_monthly}
                series={[
                    { key: 'expenses', label: 'Expenses', color: '#db2777', type: 'area', axis: 'left' },
                    {
                        key: 'maintenance',
                        label: 'Maintenance requests',
                        color: '#f59e0b',
                        type: 'bar',
                        axis: 'right',
                    },
                ]}
            />

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
            <StatsCards
                label={title}
                items={[
                    {
                        title: title,
                        value: stats.leases_count,
                        description: 'Total leases',
                        icon: <FileText />,
                    },
                    {
                        title: 'Active',
                        value: stats.active_leases,
                        description: 'Currently active',
                        icon: <Activity />,
                    },
                    {
                        title: 'Ended',
                        value: stats.ended_leases,
                        description: 'Completed',
                        icon: <Archive />,
                    },
                    {
                        title: 'Total rent',
                        value: `$${Number(stats.total_rent).toLocaleString()}`,
                        description: 'Lifetime rent',
                        icon: <Wallet />,
                    },
                    ...(stats.maintenance_open !== undefined
                        ? [
                              {
                                  title: 'Open maintenance',
                                  value: stats.maintenance_open,
                                  description: 'Requests on your unit',
                                  icon: <Wrench />,
                              },
                          ]
                        : []),
                ]}
            />

            {stats.home_monthly && (
                <ComboChart
                    title="Your home"
                    data={stats.home_monthly}
                    series={[
                        { key: 'rent', label: 'Rent', color: '#0891b2', type: 'area', axis: 'left' },
                        { key: 'maintenance', label: 'Maintenance', color: '#f59e0b', type: 'bar', axis: 'right' },
                    ]}
                />
            )}

            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0">
                    <CardTitle className="text-base font-medium">Recent {title.toLowerCase()}</CardTitle>
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
