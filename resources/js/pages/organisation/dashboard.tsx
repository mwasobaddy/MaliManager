import { Head, Link, router } from '@inertiajs/react';
import {
    Banknote,
    Building2,
    CalendarClock,
    CheckCircle2,
    CircleDashed,
    DoorOpen,
    KeyRound,
    TriangleAlert,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import type { ComboPoint } from '@/components/dashboard/combo-chart';
import { ComboChart } from '@/components/dashboard/combo-chart';
import { StatsCards } from '@/components/dashboard/stats-cards';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard, index } from '@/routes/tenant/properties';

const MONTHS = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

type Organization = {
    id: number;
    name: string;
    slug: string;
};

type Property = {
    id: number;
    name: string;
    slug: string;
    city: string | null;
    status: string;
    total_units: number;
    occupied: number;
    vacant: number;
    in_maintenance: number;
    occupancy_rate: number;
    rent_potential: number;
    open_maintenance: number;
    active_leases: number;
};

type ExpiringLease = {
    name: string;
    occupant: string | null;
    ends_at: string | null;
    days_left: number;
};

type Flag = {
    severity: string;
    message: string;
};

type Props = {
    organization: Organization;
    available_years: number[];
    properties: Property[];
    properties_count: number;
    total_units: number;
    occupied: number;
    vacant: number;
    in_maintenance: number;
    open_maintenance: number;
    active_leases: number;
    rent_potential: number;
    occupancy_rate: number;
    operations_monthly: ComboPoint[];
    expiring_leases: ExpiringLease[];
    flags: Flag[];
    year: number | null;
    month: number | null;
};

export default function OrganizationDashboard({
    organization,
    available_years,
    properties,
    properties_count,
    total_units,
    occupied,
    vacant,
    in_maintenance,
    open_maintenance,
    active_leases,
    rent_potential,
    occupancy_rate,
    operations_monthly,
    expiring_leases,
    flags,
    year,
    month,
}: Props) {
    const [filterYear, setFilterYear] = useState<string>(
        year ? String(year) : '',
    );
    const [filterMonth, setFilterMonth] = useState<string>(
        month ? String(month) : '',
    );
    const ccy = (value: number) => Number(value).toLocaleString();

    const updateFilters = (newYear: string, newMonth: string) => {
        setFilterYear(newYear);
        setFilterMonth(newMonth);

        const params: Record<string, string> = {};

        if (newYear) {
            params.year = newYear;
        }

        if (newMonth) {
            params.month = newMonth;
        }

        router.get(window.location.pathname, params, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title={`${organization.name} overview`} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title={`${organization.name} overview`}
                        description={`${properties_count} ${properties_count === 1 ? 'property' : 'properties'} across your portfolio`}
                    />

                    <div className="flex items-center gap-2">
                        <Select
                            value={filterYear}
                            onValueChange={(value) =>
                                updateFilters(value, filterMonth)
                            }
                        >
                            <SelectTrigger className="w-[100px]">
                                <SelectValue placeholder="Year" />
                            </SelectTrigger>
                            <SelectContent>
                                {available_years.map((y) => (
                                    <SelectItem key={y} value={String(y)}>
                                        {y}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={filterMonth}
                            onValueChange={(value) =>
                                updateFilters(filterYear, value)
                            }
                        >
                            <SelectTrigger className="w-[110px]">
                                <SelectValue placeholder="All months" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">All months</SelectItem>
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

                <StatsCards
                    label="Portfolio overview"
                    summary={`${occupancy_rate}% occupied`}
                    items={[
                        {
                            title: 'Properties',
                            value: properties_count,
                            description: 'Active assets',
                            icon: <Building2 />,
                        },
                        {
                            title: 'Total units',
                            value: total_units,
                            description: `${occupied} occupied`,
                            icon: <DoorOpen />,
                        },
                        {
                            title: 'Occupancy rate',
                            value: `${occupancy_rate}%`,
                            description: `${vacant} vacant`,
                            icon: <CheckCircle2 />,
                        },
                        {
                            title: 'In maintenance',
                            value: in_maintenance,
                            description: `${open_maintenance} open requests`,
                            icon: <CircleDashed />,
                        },
                        {
                            title: 'Active leases',
                            value: active_leases,
                            description: 'Across all properties',
                            icon: <KeyRound />,
                        },
                        {
                            title: 'Monthly rent potential',
                            value: ccy(rent_potential),
                            description: 'If fully occupied',
                            icon: <Banknote />,
                        },
                    ]}
                />

                <ComboChart
                    title="Operations"
                    series={[
                        {
                            key: 'expenses',
                            label: 'Expenses',
                            color: 'var(--color-destructive)',
                            type: 'area',
                        },
                        {
                            key: 'maintenance',
                            label: 'Maintenance',
                            color: 'var(--color-warning)',
                            type: 'bar',
                        },
                        {
                            key: 'new_leases',
                            label: 'New leases',
                            color: 'var(--color-brand-accent)',
                            type: 'line',
                        },
                    ]}
                    data={operations_monthly}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base font-medium">
                            Properties
                        </CardTitle>
                        <CardDescription>
                            How each asset is performing right now.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="pb-2 pr-4 font-medium">
                                            Property
                                        </th>
                                        <th className="pb-2 pr-4 font-medium">
                                            Units
                                        </th>
                                        <th className="pb-2 pr-4 font-medium">
                                            Occupied
                                        </th>
                                        <th className="pb-2 pr-4 font-medium">
                                            Vacant
                                        </th>
                                        <th className="pb-2 pr-4 font-medium">
                                            Occupancy
                                        </th>
                                        <th className="pb-2 pr-4 font-medium">
                                            Open maintenance
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Monthly rent
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {properties.map((property) => (
                                        <tr
                                            key={property.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4">
                                                <Link
                                                    href={dashboard(
                                                        property.slug,
                                                    )}
                                                    className="inline-flex items-center gap-1 font-medium text-foreground hover:underline"
                                                >
                                                    {property.name}
                                                    {property.city && (
                                                        <span className="font-normal text-muted-foreground">
                                                            · {property.city}
                                                        </span>
                                                    )}
                                                </Link>
                                            </td>
                                            <td className="py-3 pr-4 tabular-nums">
                                                {property.total_units}
                                            </td>
                                            <td className="py-3 pr-4 tabular-nums text-emerald-600 dark:text-emerald-400">
                                                {property.occupied}
                                                {property.in_maintenance > 0 && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        + {property.in_maintenance}{' '}
                                                        maint.
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-3 pr-4 tabular-nums text-muted-foreground">
                                                {property.vacant}
                                            </td>
                                            <td className="py-3 pr-4 tabular-nums">
                                                {property.occupancy_rate}%
                                            </td>
                                            <td className="py-3 pr-4 tabular-nums">
                                                {property.open_maintenance}
                                            </td>
                                            <td className="py-3 tabular-nums">
                                                KES {ccy(property.rent_potential)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base font-medium">
                                <CalendarClock className="size-4 text-muted-foreground" />
                                Expiring leases
                            </CardTitle>
                            <CardDescription>
                                Active leases ending in the next 60 days.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {expiring_leases.length === 0 ? (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    No leases are expiring soon.
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {expiring_leases.map((lease, index) => (
                                        <li
                                            key={index}
                                            className="flex items-center justify-between gap-4 text-sm"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {lease.name}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {lease.occupant ||
                                                        'No occupant'}
                                                </p>
                                            </div>
                                            <Badge
                                                variant={
                                                    lease.days_left <= 14
                                                        ? 'destructive'
                                                        : 'secondary'
                                                }
                                            >
                                                {lease.days_left} days left
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base font-medium">
                                <TriangleAlert className="size-4 text-muted-foreground" />
                                Needs attention
                            </CardTitle>
                            <CardDescription>
                                Flags worth reviewing across your portfolio.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {flags.length === 0 ? (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    Nothing needs attention right now.
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {flags.map((flag, index) => (
                                        <li
                                            key={index}
                                            className="flex items-start gap-2 text-sm"
                                        >
                                            <Wrench className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                            <span>{flag.message}</span>
                                            <Badge
                                                variant={
                                                    flag.severity === 'high'
                                                        ? 'destructive'
                                                        : 'secondary'
                                                }
                                                className="ml-auto shrink-0"
                                            >
                                                {flag.severity}
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

OrganizationDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: index(),
        },
        {
            title: 'Overview',
        },
    ],
};