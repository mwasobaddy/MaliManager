import { Head, router, useForm } from '@inertiajs/react';
import { Gauge } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import * as meterReadingRoutes from '@/routes/tenant/meter-readings';
import type {
    MeterReadingFilters,
    MeterReadingFormData,
    PaginatedReadings,
    UnitOption,
    UtilityOption,
} from '@/types/meter-reading';

type Props = {
    readings: PaginatedReadings;
    units: UnitOption[];
    utilities: UtilityOption[];
    filters: MeterReadingFilters;
};

/**
 * Utility meter readings.
 *
 * Validation mirrors `StoreMeterReadingRequest` — required unit, a known
 * utility, a non-negative reading and a date that is not in the future. The
 * client copy exists so a mistake is caught before a round trip; the server
 * remains the authority and re-checks all of it.
 */
export default function MeterReadings({
    readings,
    units,
    utilities,
    filters,
}: Props) {
    const form = useForm<MeterReadingFormData>({
        unit_id: filters.unit_id ? String(filters.unit_id) : '',
        utility: '',
        reading: '',
        read_on: new Date().toISOString().slice(0, 10),
        notes: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(meterReadingRoutes.store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('reading', 'notes'),
        });
    };

    const filterByUnit = (value: string) => {
        router.get(
            meterReadingRoutes.index.url({
                query: {
                    unit_id: value === 'all' ? undefined : value,
                    utility: filters.utility || undefined,
                },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const filterByUtility = (value: string) => {
        router.get(
            meterReadingRoutes.index.url({
                query: {
                    unit_id: filters.unit_id ?? undefined,
                    utility: value === 'all' ? undefined : value,
                },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title="Meter readings" />

            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    level="h1"
                    title="Meter readings"
                    description="Record utility consumption for a unit. Consumption is calculated from the previous reading."
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="text-base font-medium">
                                Record a reading
                            </CardTitle>
                            <CardDescription>
                                One reading per unit, utility and day.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="unit_id">Unit</Label>
                                    <Select
                                        value={form.data.unit_id}
                                        onValueChange={(value) =>
                                            form.setData('unit_id', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="unit_id"
                                            aria-invalid={!!form.errors.unit_id}
                                        >
                                            <SelectValue placeholder="Choose a unit" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {units.map((unit) => (
                                                <SelectItem
                                                    key={unit.value}
                                                    value={String(unit.value)}
                                                >
                                                    {unit.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.unit_id && (
                                        <p className="text-sm text-status-danger">
                                            {form.errors.unit_id}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="utility">Utility</Label>
                                    <Select
                                        value={form.data.utility}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'utility',
                                                value as MeterReadingFormData['utility'],
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="utility"
                                            aria-invalid={!!form.errors.utility}
                                        >
                                            <SelectValue placeholder="Choose a utility" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {utilities.map((utility) => (
                                                <SelectItem
                                                    key={utility.value}
                                                    value={utility.value}
                                                >
                                                    {utility.label} (
                                                    {utility.unit_of_measure})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.utility && (
                                        <p className="text-sm text-status-danger">
                                            {form.errors.utility}
                                        </p>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="reading">Reading</Label>
                                        <Input
                                            id="reading"
                                            type="number"
                                            inputMode="decimal"
                                            step="0.001"
                                            min="0"
                                            value={form.data.reading}
                                            onChange={(event) =>
                                                form.setData(
                                                    'reading',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={!!form.errors.reading}
                                        />
                                        {form.errors.reading && (
                                            <p className="text-sm text-status-danger">
                                                {form.errors.reading}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="read_on">Read on</Label>
                                        <Input
                                            id="read_on"
                                            type="date"
                                            max={new Date()
                                                .toISOString()
                                                .slice(0, 10)}
                                            value={form.data.read_on}
                                            onChange={(event) =>
                                                form.setData(
                                                    'read_on',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={!!form.errors.read_on}
                                        />
                                        {form.errors.read_on && (
                                            <p className="text-sm text-status-danger">
                                                {form.errors.read_on}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="notes">
                                        Notes{' '}
                                        <span className="font-normal text-muted-foreground">
                                            (optional)
                                        </span>
                                    </Label>
                                    <Textarea
                                        id="notes"
                                        rows={2}
                                        value={form.data.notes}
                                        onChange={(event) =>
                                            form.setData(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={!!form.errors.notes}
                                    />
                                    {form.errors.notes && (
                                        <p className="text-sm text-status-danger">
                                            {form.errors.notes}
                                        </p>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="w-full"
                                >
                                    Save reading
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="overflow-hidden">
                        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                            <div>
                                <CardTitle className="text-base font-medium">
                                    Recorded readings
                                </CardTitle>
                                <CardDescription>
                                    {readings.total.toLocaleString()} total
                                </CardDescription>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Select
                                    value={
                                        filters.unit_id
                                            ? String(filters.unit_id)
                                            : 'all'
                                    }
                                    onValueChange={filterByUnit}
                                >
                                    <SelectTrigger
                                        className="w-40"
                                        aria-label="Filter by unit"
                                    >
                                        <SelectValue placeholder="All units" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All units
                                        </SelectItem>
                                        {units.map((unit) => (
                                            <SelectItem
                                                key={unit.value}
                                                value={String(unit.value)}
                                            >
                                                {unit.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <Select
                                    value={filters.utility || 'all'}
                                    onValueChange={filterByUtility}
                                >
                                    <SelectTrigger
                                        className="w-40"
                                        aria-label="Filter by utility"
                                    >
                                        <SelectValue placeholder="All utilities" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All utilities
                                        </SelectItem>
                                        {utilities.map((utility) => (
                                            <SelectItem
                                                key={utility.value}
                                                value={utility.value}
                                            >
                                                {utility.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </CardHeader>

                        <CardContent className="px-0">
                            {readings.data.length === 0 ? (
                                <Empty
                                    icon={<Gauge className="size-4" />}
                                    title="No readings yet"
                                    description="Record the first reading for a unit and consumption will start accumulating from the next one."
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full border-collapse text-sm">
                                        <thead>
                                            <tr className="border-b border-border-strong">
                                                {[
                                                    'Unit',
                                                    'Utility',
                                                    'Reading',
                                                    'Consumption',
                                                    'Read on',
                                                    'Recorded by',
                                                ].map((heading) => (
                                                    <th
                                                        key={heading}
                                                        scope="col"
                                                        className="bg-bg-base px-3 py-2 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                                    >
                                                        {heading}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {readings.data.map((reading) => (
                                                <tr
                                                    key={reading.id}
                                                    className="border-b border-border/60 last:border-b-0 hover:bg-bg-base"
                                                >
                                                    <td className="px-3 py-2">
                                                        {reading.unit_name ??
                                                            '—'}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <Badge variant="neutral">
                                                            {
                                                                reading.utility_label
                                                            }
                                                        </Badge>
                                                    </td>
                                                    <td className="px-3 py-2 tabular-nums">
                                                        {reading.reading.toLocaleString()}{' '}
                                                        <span className="text-muted-foreground">
                                                            {
                                                                reading.unit_of_measure
                                                            }
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-2 tabular-nums">
                                                        {reading.consumption ===
                                                        null ? (
                                                            <span className="text-muted-foreground">
                                                                —
                                                            </span>
                                                        ) : (
                                                            `${reading.consumption.toLocaleString()} ${reading.unit_of_measure}`
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {reading.read_on}
                                                    </td>
                                                    <td className="px-3 py-2 text-muted-foreground">
                                                        {reading.recorder_name ??
                                                            '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>

                        {readings.last_page > 1 && (
                            <div className="px-4 pb-4">
                                <Pagination
                                    currentPage={readings.current_page}
                                    lastPage={readings.last_page}
                                    total={readings.total}
                                    label="readings"
                                    onChange={(page) =>
                                        router.get(
                                            meterReadingRoutes.index.url({
                                                query: {
                                                    page,
                                                    unit_id:
                                                        filters.unit_id ??
                                                        undefined,
                                                    utility:
                                                        filters.utility ||
                                                        undefined,
                                                },
                                            }),
                                            {},
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                            },
                                        )
                                    }
                                />
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </>
    );
}
