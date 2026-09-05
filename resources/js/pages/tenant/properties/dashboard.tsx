import { Form, Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Banknote,
    Building2,
    CheckCircle2,
    CircleDashed,
    DoorOpen,
    KeyRound,
    Plus,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import {
    Cell,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
} from 'recharts';
import type { ComboPoint } from '@/components/dashboard/combo-chart';
import { ComboChart } from '@/components/dashboard/combo-chart';
import { StatsCards } from '@/components/dashboard/stats-cards';
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
};

type Props = {
    property: Property;
    units: Unit[];
    stats: PropertyStat;
    plan: Plan;
    year: number | null;
    month: number | null;
};

export default function PropertiesDashboard({
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
    const atUnitLimit =
        plan.units_limit !== null && units.length >= plan.units_limit;

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
            <Head title={property.name} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="space-y-0.5">
                        <Heading
                            variant="small"
                            title={property.name}
                            description={`${property.city ?? 'No city set'}${property.address ? ` · ${property.address}` : ''}`}
                        />
                        <Badge
                            variant={
                                property.status === 'active'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {property.status}
                        </Badge>
                    </div>
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
                            <SelectTrigger className="w-[100px]">
                                <SelectValue placeholder="Year" />
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
                    label="Property overview"
                    summary={`${stats.total_units} units`}
                    items={[
                        {
                            title: 'Total units',
                            value: stats.total_units,
                            description: `${stats.occupied} occupied`,
                            icon: <Building2 />,
                        },
                        {
                            title: 'Occupied',
                            value: stats.occupied,
                            description: `${stats.occupancy_rate}% occupancy`,
                            icon: <CheckCircle2 />,
                        },
                        {
                            title: 'Vacant',
                            value: stats.vacant,
                            icon: <CircleDashed />,
                        },
                        {
                            title: 'In maintenance',
                            value: stats.in_maintenance,
                            icon: <Wrench />,
                        },
                        {
                            title: 'Monthly rent',
                            value: ccy(stats.rent_potential),
                            description: 'potential',
                            icon: <Banknote />,
                        },
                        {
                            title: 'Open maintenance',
                            value: stats.open_maintenance,
                            icon: <AlertTriangle />,
                        },
                        {
                            title: 'Active leases',
                            value: stats.active_leases,
                            icon: <KeyRound />,
                        },
                    ]}
                />

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <ComboChart
                            title="Property activity"
                            data={stats.operations_monthly}
                            series={[
                                {
                                    key: 'rent_roll',
                                    label: 'Rent roll',
                                    color: '#2563eb',
                                    type: 'area',
                                    axis: 'left',
                                },
                                {
                                    key: 'maintenance',
                                    label: 'Maintenance',
                                    color: '#f97316',
                                    type: 'bar',
                                    axis: 'right',
                                },
                                {
                                    key: 'new_leases',
                                    label: 'New leases',
                                    color: '#22c55e',
                                    type: 'line',
                                    axis: 'right',
                                },
                            ]}
                        />
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base font-medium">
                                Unit status
                            </CardTitle>
                            <CardDescription>
                                Composition by status
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={260}>
                                <PieChart>
                                    <Pie
                                        data={stats.unit_status}
                                        dataKey="count"
                                        nameKey="status"
                                        cx="50%"
                                        cy="50%"
                                        outerRadius={90}
                                        label
                                    >
                                        {stats.unit_status.map((entry) => (
                                            <Cell
                                                key={entry.status}
                                                fill={entry.color}
                                            />
                                        ))}
                                    </Pie>
                                    <Tooltip />
                                    <Legend />
                                </PieChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between gap-4">
                            <div className="flex items-center gap-2">
                                <DoorOpen className="size-4 text-muted-foreground" />
                                <CardTitle>Units</CardTitle>
                                <CardDescription>
                                    {units.length} total
                                </CardDescription>
                            </div>
                            {!addUnit && (
                                <Button
                                    size="sm"
                                    onClick={() => setAddUnit(true)}
                                    disabled={atUnitLimit}
                                    title={
                                        atUnitLimit
                                            ? 'Your plan allows no more units'
                                            : undefined
                                    }
                                >
                                    <Plus className="size-4" />
                                    Add unit
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {atUnitLimit && (
                            <p className="mb-4 text-sm text-destructive">
                                Your current plan allows up to{' '}
                                {plan.units_limit} units. Please upgrade your
                                plan to add more.
                            </p>
                        )}

                        {addUnit && (
                            <Form
                                method="post"
                                action={storeUnit(property.slug)}
                                options={{ preserveScroll: true }}
                                className="mb-6 grid gap-4 rounded-lg border border-input p-4 md:grid-cols-[1fr_1fr_1fr_auto]"
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
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing ? 'Adding…' : 'Add'}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                onClick={() =>
                                                    setAddUnit(false)
                                                }
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                        <InputError message={errors.plan} />
                                    </>
                                )}
                            </Form>
                        )}

                        {units.length === 0 ? (
                            <div className="py-10 text-center">
                                <Building2 className="mx-auto size-8 text-muted-foreground" />
                                <p className="mt-3 text-sm font-medium">
                                    No units yet
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Add your first unit to start managing tenants
                                    and leases.
                                </p>
                                {!addUnit && (
                                    <Button
                                        className="mt-4"
                                        onClick={() => setAddUnit(true)}
                                        disabled={atUnitLimit}
                                        title={
                                            atUnitLimit
                                                ? 'Your plan allows no more units'
                                                : undefined
                                        }
                                    >
                                        <Plus className="size-4" />
                                        Add your first unit
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <div className="divide-y divide-border rounded-lg border">
                                {units.map((unit) => (
                                    <div
                                        key={unit.id}
                                        className="flex items-center justify-between gap-4 px-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {unit.name}
                                            </p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {unit.type ?? 'Unit'}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            {unit.monthly_rent !== null && (
                                                <p className="text-sm font-medium">
                                                    {Number(
                                                        unit.monthly_rent,
                                                    ).toLocaleString()}
                                                </p>
                                            )}
                                            <Badge
                                                variant={
                                                    unit.status === 'vacant'
                                                        ? 'secondary'
                                                        : 'default'
                                                }
                                            >
                                                {unit.status}
                                            </Badge>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
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
