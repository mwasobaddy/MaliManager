import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { DoorOpen } from 'lucide-react';
import { useState } from 'react';
import type { ComboPoint } from '@/components/dashboard/combo-chart';
import { ComboChart } from '@/components/dashboard/combo-chart';
import { StatsCards } from '@/components/dashboard/stats-cards';
import { AlertsPanel } from '@/components/platform/alerts-panel';
import { ArrearsAgingCard } from '@/components/platform/arrears-aging';
import { DefaulterTable } from '@/components/platform/defaulter-table';
import { RentCollectionHero } from '@/components/platform/rent-collection-hero';
import { UnitGridCard } from '@/components/platform/unit-grid';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import { formatMoney } from '@/lib/format-money';
import { index } from '@/routes/tenant/properties';
import { store as storeUnit } from '@/routes/tenant/properties/units';

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

type Property = {
    id: number;
    name: string;
    slug: string;
    address: string | null;
    city: string | null;
    status: string;
};

type Unit = {
    id: number;
    name: string;
    type: string | null;
    status: string;
    monthly_rent: string | null;
};

type Organization = {
    id: number;
    name: string;
    slug: string;
    currency: string;
};

type Plan = {
    units_limit: number | null;
};

type UnitStatus = {
    status: string;
    count: number;
    color: string;
};

type PropertyStat = {
    available_years: number[];
    total_units: number;
    occupied: number;
    vacant: number;
    in_maintenance: number;
    occupancy_rate: number;
    rent_potential: number;
    open_maintenance: number;
    active_leases: number;
    unit_status: UnitStatus[];
    operations_monthly: ComboPoint[];
    collection: {
        expected_mtd: string;
        collected_mtd: string;
        collection_rate: number | null;
        arrears_total: string;
        overdue_count: number;
        aging: Array<{ bucket: string; total: string; count: number }>;
        defaulters: Array<{
            lease_id: number;
            occupant: string;
            unit: string | null;
            property: string;
            property_slug: string;
            outstanding: string;
            days_overdue: number;
            can_remind: boolean;
            remind_throttled: boolean;
            last_reminded_ago: string | null;
        }>;
    };
    attention: Array<{ severity: 'danger' | 'warning' | 'info'; text: string }>;
    expiring_leases: Array<{
        unit: string | null;
        occupant: string | null;
        ends_at: string;
        days_left: number;
    }>;
    units_grid: Array<{
        property: string;
        units: Array<{
            id: number;
            name: string;
            status: string;
            overdue: boolean;
            occupant: string | null;
            type: string | null;
            monthly_rent: string | null;
        }>;
    }>;
};

type Props = {
    organization: Organization;
    property: Property;
    units: Unit[];
    stats: PropertyStat;
    plan: Plan;
    year: number | null;
    month: number | null;
};

export default function PropertiesDashboard({
    organization,
    property,
    units,
    stats,
    plan,
    year,
    month,
}: Props) {
    const [addUnit, setAddUnit] = useState(false);
    const [filterYear, setFilterYear] = useState<string>(
        year ? String(year) : '',
    );
    const [filterMonth, setFilterMonth] = useState<string>(
        month ? String(month) : '',
    );
    const pageUrl = usePage().url.split('?')[0];
    const atUnitLimit =
        plan.units_limit !== null && units.length >= plan.units_limit;

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

        router.get(pageUrl, params, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title={property.name} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                {/* Hero band over the property line-art. The image is
                    near-white, so a bottom-up background scrim carries the
                    title in both themes while the drawing shows through. */}
                <div className="relative overflow-hidden rounded-2xl border">
                    <img
                        src="/images/property-hero.jpg"
                        alt=""
                        aria-hidden
                        className="absolute inset-0 h-full w-full object-cover object-center"
                    />
                    <div
                        aria-hidden
                        className="absolute inset-0 bg-gradient-to-t from-background via-background/55 to-transparent"
                    />
                    <div className="relative flex flex-wrap items-end justify-between gap-4 px-6 pt-16 pb-5 sm:px-8">
                        <div className="space-y-0.5">
                            <Heading
                                variant="small"
                                level="h1"
                                title={property.name}
                                description={
                                    [property.city, property.address]
                                        .filter(Boolean)
                                        .join(' · ') || undefined
                                }
                            />
                            <Badge
                                variant={
                                    property.status === 'active'
                                        ? 'default'
                                        : 'secondary'
                                }
                                className="capitalize"
                            >
                                {property.status}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground tabular-nums">
                            {stats.total_units}{' '}
                            {stats.total_units === 1 ? 'unit' : 'units'} ·{' '}
                            {stats.occupancy_rate}% occupied
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Button asChild variant="outline">
                        <Link href={index()}>All properties</Link>
                    </Button>

                    <div className="flex items-center gap-2">
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
                                {stats.available_years.map((y) => (
                                    <SelectItem key={y} value={String(y)}>
                                        {y}
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
                                <SelectItem value="all">All months</SelectItem>
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
                    label="Property overview"
                    summary={`${stats.total_units} units`}
                    items={[
                        {
                            label: 'Total Units',
                            value: stats.total_units,
                            variant: 'accent',
                        },
                        {
                            label: 'Occupied Units',
                            value: stats.occupied,
                            variant: 'accent',
                        },
                        {
                            label: 'Vacant Units',
                            value: stats.vacant,
                            variant: 'accent',
                        },
                        {
                            label: 'Monthly Rent Potential',
                            value: formatMoney(
                                stats.rent_potential,
                                organization.currency,
                            ),
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

                {stats.expiring_leases.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base font-medium">
                                Leases expiring soon
                            </CardTitle>
                            <CardDescription>
                                Renewals and rent reviews due within 60 days.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-2 text-sm">
                                {stats.expiring_leases.map((lease) => (
                                    <li
                                        key={`${lease.unit}-${lease.ends_at}`}
                                        className="flex items-center justify-between gap-2"
                                    >
                                        <span>
                                            {lease.occupant} — {lease.unit}
                                        </span>
                                        <span className="text-muted-foreground tabular-nums">
                                            {lease.ends_at} · {lease.days_left}d
                                            left
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <UnitGridCard
                    grid={stats.units_grid}
                    currency={organization.currency}
                    onAdd={() => setAddUnit(true)}
                    addDisabledReason={
                        atUnitLimit ? 'Your plan allows no more units' : null
                    }
                    addTooltip="Add a unit to this property"
                />

                {atUnitLimit && (
                    <p className="text-sm text-destructive">
                        Your current plan allows up to {plan.units_limit} units.
                        Please upgrade your plan to add more.
                    </p>
                )}

                {addUnit && (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <DoorOpen className="size-4 text-muted-foreground" />
                                <CardTitle>Add unit</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Form
                                method="post"
                                action={storeUnit(property.slug)}
                                options={{ preserveScroll: true }}
                                className="grid gap-4 md:grid-cols-[1fr_1fr_1fr_auto]"
                                onSuccess={() => setAddUnit(false)}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="unit-name">
                                                Unit name
                                            </Label>
                                            <Input
                                                id="unit-name"
                                                name="name"
                                                type="text"
                                                required
                                                placeholder="A1"
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="unit-type">
                                                Type
                                            </Label>
                                            <Input
                                                id="unit-type"
                                                name="type"
                                                type="text"
                                                placeholder="1 Bedroom"
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="unit-rent">
                                                Monthly rent
                                            </Label>
                                            <Input
                                                id="unit-rent"
                                                name="monthly_rent"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                placeholder="25000"
                                            />
                                            <InputError
                                                message={errors.monthly_rent}
                                            />
                                        </div>
                                        <div className="flex items-end gap-2">
                                            <Button
                                                type="button"
                                                variant="shadcndefault"
                                                onClick={() =>
                                                    setAddUnit(false)
                                                }
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing ? 'Adding…' : 'Add'}
                                            </Button>
                                        </div>
                                        <InputError message={errors.plan} />
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                <ComboChart
                    title="Property activity"
                    data={stats.operations_monthly}
                    series={[
                        {
                            key: 'rent_roll',
                            label: 'Rent roll',
                            color: 'var(--chart-1)',
                            type: 'area',
                            axis: 'left',
                        },
                        {
                            key: 'maintenance',
                            label: 'Maintenance',
                            color: 'var(--chart-2)',
                            type: 'bar',
                            axis: 'right',
                        },
                        {
                            key: 'new_leases',
                            label: 'New leases',
                            color: 'var(--chart-3)',
                            type: 'line',
                            axis: 'right',
                        },
                    ]}
                />
            </div>
        </>
    );
}

PropertiesDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: index(),
        },
        {
            title: 'Dashboard',
            href: '',
        },
    ],
};
