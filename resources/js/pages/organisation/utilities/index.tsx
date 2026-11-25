import { Head, router } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    Check,
    ChevronRight,
    Circle,
    ClipboardList,
    Download,
    Gauge,
    ListFilter,
    MoreHorizontal,
    Plus,
    Search,
    SquarePen,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import ConfirmDeleteOverlay from '@/components/confirm-delete-overlay';
import { downloadCsv } from '@/components/csv-actions';
import { CsvExportSheet } from '@/components/csv-export-sheet';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { LabelledIconButton } from '@/components/labelled-icon-button';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { TableOverflowMenu } from '@/components/table-overflow-menu';
import { Textarea } from '@/components/ui/textarea';
import { useOverlayParam } from '@/hooks/use-overlay-param';
import { formatMoney } from '@/lib/format-money';
import {
    destroy as destroyUtilityCharge,
    store as storeUtilityCharge,
    update as updateUtilityCharge,
} from '@/routes/tenant/utilities/charges';
import { index as unitsIndex } from '@/routes/tenant/units';
import {
    bulkStore as bulkStoreUtilityReadings,
    exportMethod as exportUtilities,
    index as utilitiesIndex,
    store as storeUtilityReading,
} from '@/routes/tenant/utilities';
import type {
    ChargeProperty,
    PaginatedReadings,
    ReadingGrid,
    SetupProperty,
    UnitOption,
    UtilityChargeRow,
    UtilityOption,
    UtilityReading,
    UtilityReadingFilters,
} from '@/types/utility';

type Props = {
    readings: PaginatedReadings;
    focusedReading: UtilityReading | null;
    units: UnitOption[];
    properties: ChargeProperty[];
    charges: UtilityChargeRow[];
    utilities: UtilityOption[];
    setup: SetupProperty[];
    readingGrid: ReadingGrid;
    filters: UtilityReadingFilters;
};

/** Debounce so a fast typist issues one request, not one per keystroke. */
const SEARCH_DEBOUNCE_MS = 350;

export default function UtilitiesIndex({
    readings,
    focusedReading,
    units,
    properties = [],
    charges = [],
    utilities,
    setup = [],
    readingGrid,
}: Props) {
    const { query, setOverlayParam } = useOverlayParam();
    const { context } = usePage().props;
    // Charge setup (names, kinds, rates) is landlord work behind
    // utility.manage. Recording readings needs only unit.manage, so the
    // grid and the single sheet stay open to caretakers.
    const canManageCharges = (context?.permissions ?? []).includes(
        'utility.manage',
    );
    const [isTableLoading, setIsTableLoading] = useState(false);
    const [isMoreOpen, setIsMoreOpen] = useState(false);
    const [createUtility, setCreateUtility] = useState('');

    const filters = {
        search: query.get('search') ?? '',
        unit: query.get('unit_id') ?? '',
        utility: query.get('utility') ?? '',
    };

    const sort = {
        column: query.get('sort') || null,
        direction: query.get('direction') === 'asc' ? 'asc' : 'desc',
    };

    const isFiltersOpen =
        query.get('filters') !== null && query.get('filters') !== '';
    const isExportOpen =
        query.get('export') !== null && query.get('export') !== '';
    const isCreateOpen = query.get('create') === '1';

    // Bulk entry: one walked route per sheet. The property picker reloads
    // only the grid rows; the shared date defaults to today and each row
    // posts just unit, utility and reading — rates belong to the charge
    // and are shown read-only so a caretaker can never reprice.
    const isBulkOpen = query.get('bulk') === 'new';
    const [gridPropertyId, setGridPropertyId] = useState<string>('');
    const [gridReadOn, setGridReadOn] = useState(
        new Date().toISOString().slice(0, 10),
    );
    const [gridValues, setGridValues] = useState<Record<string, string>>({});

    useEffect(() => {
        setGridPropertyId(
            readingGrid?.property_id != null
                ? String(readingGrid.property_id)
                : '',
        );
    }, [readingGrid?.property_id]);

    function openBulkReadings() {
        setGridValues({});
        setGridReadOn(new Date().toISOString().slice(0, 10));
        setOverlayParam('bulk', 'new');
    }

    function closeBulkReadings() {
        setGridValues({});
        setOverlayParam('bulk', null, { replace: true });
    }

    function pickGridProperty(value: string) {
        setGridPropertyId(value);
        setGridValues({});
        setOverlayParam('grid_property', value, {
            replace: true,
            only: ['readingGrid'],
        });
    }

    const gridRows = readingGrid?.rows ?? [];
    const filledGridRows = gridRows.filter(
        (row) =>
            (gridValues[`${row.unit_id}:${row.utility}`] ?? '').trim() !== '',
    );

    // The record is a sheet on this page, so the id travels in the URL and
    // the server resolves it into `focusedReading`. Falling back to the
    // visible rows keeps opening from the list a single request.
    const viewedReadingId = Number(query.get('reading'));
    const isViewOpen = query.get('mode') === 'view' && viewedReadingId > 0;
    const viewedReading = isViewOpen
        ? (focusedReading ??
          readings.data.find((reading) => reading.id === viewedReadingId) ??
          null)
        : null;

    function openView(reading: UtilityReading) {
        setOverlayParam('mode', 'view', {
            only: ['readings', 'focusedReading'],
            keep: { reading: String(reading.id) },
        });
    }

    function closeView() {
        setOverlayParam('mode', null, {
            replace: true,
            only: ['readings'],
            keep: { reading: '' },
        });
    }

    // Utility charges: landlord-configured billing (metered rates, fixed
    // per-unit fees, shared master bills) assigned to the units that
    // pay them. Single fees live on each lease instead.
    const chargeParam = query.get('charge');
    const isChargeCreateOpen = chargeParam === 'new';
    const chargeId = Number(chargeParam);
    const editCharge =
        Number.isFinite(chargeId) && chargeId > 0
            ? (charges.find((charge) => charge.id === chargeId) ?? null)
            : null;
    const isChargeEditOpen = editCharge !== null;
    const [deleteCharge, setDeleteCharge] = useState<UtilityChargeRow | null>(
        null,
    );

    function openChargeCreate(propertyId?: number) {
        resetChargeForm(propertyId);
        setOverlayParam('charge', 'new');
    }

    function openChargeEdit(charge: UtilityChargeRow) {
        seedChargeForm(charge);
        setOverlayParam('charge', String(charge.id), {
            only: ['charges'],
        });
    }

    function closeChargeSheets() {
        setOverlayParam('charge', null, {
            replace: true,
            only: ['readings', 'charges'],
        });
    }

    const [chargePropertyId, setChargePropertyId] = useState('');
    const [chargeKind, setChargeKind] = useState<
        'metered' | 'fixed' | 'shared'
    >('metered');
    const [chargeUtility, setChargeUtility] = useState('');
    const [chargeRate, setChargeRate] = useState('');
    const [chargeCurrency, setChargeCurrency] = useState('KES');
    const [chargeIsActive, setChargeIsActive] = useState(true);
    const [chargeUnitIds, setChargeUnitIds] = useState<number[]>([]);
    const [chargeAllocation, setChargeAllocation] = useState('equal');
    const [chargeMasterAmount, setChargeMasterAmount] = useState('');
    const [chargeName, setChargeName] = useState('');
    const [chargeAmounts, setChargeAmounts] = useState<Record<number, string>>(
        {},
    );

    /**
     * Common starting points so a landlord picks what tenants pay for
     * instead of learning billing vocabulary first. A preset fills the
     * name, kind and utility; the landlord still types the numbers, and
     * units stay ticked from the pre-select below.
     */
    const CHARGE_PRESETS = [
        { name: 'Water', kind: 'metered', utility: 'water' },
        { name: 'Electricity', kind: 'metered', utility: 'electricity' },
        { name: 'Gas', kind: 'metered', utility: 'gas' },
        { name: 'Garbage', kind: 'fixed', utility: '' },
        { name: 'Security', kind: 'fixed', utility: '' },
    ] as const;

    function applyChargePreset(preset: (typeof CHARGE_PRESETS)[number]) {
        setChargeName(preset.name);
        setChargeKind(preset.kind);
        setChargeUtility(preset.utility);
    }

    function unitIdsForProperty(propertyId: string): number[] {
        return (
            properties
                .find((property) => String(property.id) === propertyId)
                ?.units.map((unit) => unit.id) ?? []
        );
    }

    function resetChargeForm(defaultPropertyId?: number) {
        const fallback = properties.length > 0 ? String(properties[0].id) : '';
        const propertyId =
            defaultPropertyId !== undefined &&
            properties.some((p) => p.id === defaultPropertyId)
                ? String(defaultPropertyId)
                : fallback;
        setChargePropertyId(propertyId);
        setChargeKind('metered');
        setChargeUtility('');
        setChargeRate('');
        setChargeCurrency('KES');
        setChargeIsActive(true);
        // New charges start with every unit ticked — unticking the few
        // that don't pay is faster than ticking dozens, and a charge with
        // no units bills nothing, which reads as broken.
        setChargeUnitIds(unitIdsForProperty(propertyId));
        setChargeAllocation('equal');
        setChargeMasterAmount('');
        setChargeName('');
        setChargeAmounts({});
    }

    function seedChargeForm(charge: UtilityChargeRow) {
        setChargePropertyId(String(charge.property_id));
        setChargeKind(charge.kind);
        setChargeUtility(charge.utility ?? '');
        setChargeRate(charge.rate ?? '');
        setChargeCurrency(charge.currency);
        setChargeIsActive(charge.is_active);
        setChargeUnitIds(charge.units.map((unit) => unit.id));
        setChargeAllocation(charge.allocation ?? 'equal');
        setChargeMasterAmount(charge.master_amount ?? '');
        setChargeAmounts(
            Object.fromEntries(
                charge.units
                    .filter((unit) => unit.amount !== null)
                    .map((unit) => [unit.id, unit.amount as string]),
            ),
        );
    }

    const chargeUnits =
        properties.find((property) => String(property.id) === chargePropertyId)
            ?.units ?? [];

    function toggleChargeUnit(id: number) {
        setChargeUnitIds((current) => {
            const next = current.includes(id)
                ? current.filter((uid) => uid !== id)
                : [...current, id];

            if (!next.includes(id)) {
                setChargeAmounts((amounts) => {
                    const rest = { ...amounts };
                    delete rest[id];
                    return rest;
                });
            }

            return next;
        });
    }

    const needsAmounts = chargeKind === 'fixed';
    function chargeFields(
        errors: Record<string, string>,
        idPrefix: string,
        mode: 'create' | 'edit',
    ) {
        return (
            <>
                {mode === 'create' ? (
                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}-property`}>Property</Label>
                        <SearchableSelect
                            id={`${idPrefix}-property`}
                            name="property_id"
                            options={properties.map((property) => ({
                                value: String(property.id),
                                label: property.name,
                            }))}
                            value={chargePropertyId}
                            onValueChange={(value) => {
                                setChargePropertyId(value);
                                setChargeUnitIds(unitIdsForProperty(value));
                                setChargeAmounts({});
                            }}
                            placeholder="Choose a property"
                            searchPlaceholder="Search properties…"
                        />
                        <InputError message={errors.property_id} />
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {
                            properties.find(
                                (property) =>
                                    String(property.id) === chargePropertyId,
                            )?.name
                        }
                    </p>
                )}

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-name`}>Charge name</Label>
                    {mode === 'create' ? (
                        <Input
                            id={`${idPrefix}-name`}
                            name="name"
                            placeholder="Water"
                            value={chargeName}
                            onChange={(event) =>
                                setChargeName(event.target.value)
                            }
                        />
                    ) : (
                        <Input
                            id={`${idPrefix}-name`}
                            name="name"
                            placeholder="Water"
                            defaultValue={editCharge?.name ?? ''}
                        />
                    )}
                    <InputError message={errors.name} />
                </div>

                {mode === 'create' && (
                    <div className="grid gap-2">
                        <span className="text-sm leading-none font-medium">
                            Start from a preset{' '}
                            <span className="font-normal text-muted-foreground">
                                (optional)
                            </span>
                        </span>
                        <div className="flex flex-wrap gap-2">
                            {CHARGE_PRESETS.map((preset) => (
                                <Button
                                    key={preset.name}
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => applyChargePreset(preset)}
                                >
                                    {preset.name}
                                </Button>
                            ))}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Fills in the name and billing type — you still set
                            the rate below.
                        </p>
                    </div>
                )}

                {mode === 'create' ? (
                    <fieldset className="space-y-3">
                        <legend className="text-sm font-medium">
                            Billing type
                        </legend>
                        <input type="hidden" name="kind" value={chargeKind} />
                        <RadioGroup
                            value={chargeKind}
                            onValueChange={(value) =>
                                setChargeKind(
                                    value as 'metered' | 'fixed' | 'shared',
                                )
                            }
                            className="space-y-3"
                        >
                            {(
                                [
                                    [
                                        'metered',
                                        'Metered',
                                        'Billed per unit used, at the rate below.',
                                    ],
                                    [
                                        'fixed',
                                        'Every period',
                                        'A flat amount per unit, every period.',
                                    ],
                                    [
                                        'shared',
                                        'Shared bill',
                                        "One master bill (e.g. the block's water bill) split across the units below.",
                                    ],
                                ] as [string, string, string][]
                            ).map(([value, label, hint]) => (
                                <div
                                    key={value}
                                    className="flex items-start gap-2"
                                >
                                    <RadioGroupItem
                                        id={`${idPrefix}-kind-${value}`}
                                        value={value}
                                        className="mt-0.5"
                                    />
                                    <div className="grid gap-0.5">
                                        <Label
                                            htmlFor={`${idPrefix}-kind-${value}`}
                                            className="cursor-pointer"
                                        >
                                            {label}
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            {hint}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </RadioGroup>
                        <InputError message={errors.kind} />
                    </fieldset>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {editCharge?.kind_label} — the billing type cannot
                        change after creation.
                    </p>
                )}

                {chargeKind === 'metered' && (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor={`${idPrefix}-utility`}>
                                Utility
                            </Label>
                            <input
                                type="hidden"
                                name="utility"
                                value={chargeUtility}
                            />
                            <Select
                                value={chargeUtility}
                                onValueChange={setChargeUtility}
                            >
                                <SelectTrigger
                                    id={`${idPrefix}-utility`}
                                    className="w-full"
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
                            <InputError message={errors.utility} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor={`${idPrefix}-rate`}>
                                Rate per unit
                            </Label>
                            <Input
                                id={`${idPrefix}-rate`}
                                name="rate"
                                type="number"
                                inputMode="decimal"
                                step="0.01"
                                min="0"
                                placeholder="120.50"
                                defaultValue={
                                    mode === 'edit'
                                        ? (editCharge?.rate ?? '')
                                        : ''
                                }
                            />
                            <InputError message={errors.rate} />
                        </div>
                    </>
                )}

                {chargeKind === 'shared' && (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor={`${idPrefix}-allocation`}>
                                Split basis
                            </Label>
                            <input
                                type="hidden"
                                name="allocation"
                                value={chargeAllocation}
                            />
                            <Select
                                value={chargeAllocation}
                                onValueChange={setChargeAllocation}
                            >
                                <SelectTrigger
                                    id={`${idPrefix}-allocation`}
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Choose a split" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="equal">
                                        Split evenly — every unit pays the same
                                        share
                                    </SelectItem>
                                    <SelectItem value="occupants">
                                        Split by occupants — units with more
                                        tenants pay more
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.allocation} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor={`${idPrefix}-master-amount`}>
                                Master bill total ({chargeCurrency})
                            </Label>
                            <Input
                                id={`${idPrefix}-master-amount`}
                                name="master_amount"
                                type="number"
                                inputMode="decimal"
                                step="0.01"
                                min="0"
                                placeholder="15000.00"
                                value={chargeMasterAmount}
                                onChange={(event) =>
                                    setChargeMasterAmount(event.target.value)
                                }
                            />
                            <InputError message={errors.master_amount} />
                        </div>
                    </>
                )}

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-currency`}>Currency</Label>
                    <input
                        type="hidden"
                        name="currency"
                        value={chargeCurrency}
                    />
                    <Select
                        value={chargeCurrency}
                        onValueChange={setChargeCurrency}
                    >
                        <SelectTrigger
                            id={`${idPrefix}-currency`}
                            className="w-full"
                        >
                            <SelectValue placeholder="Select currency" />
                        </SelectTrigger>
                        <SelectContent>
                            {['KES', 'USD', 'EUR', 'GBP'].map((value) => (
                                <SelectItem key={value} value={value}>
                                    {value}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.currency} />
                </div>

                <Collapsible className="group/collapsible">
                    <CollapsibleTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="justify-start gap-1 px-0 text-muted-foreground"
                        >
                            <ChevronRight className="size-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            Accounting (optional)
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent className="grid gap-2 pt-2">
                        <Label htmlFor={`${idPrefix}-gl-code`}>
                            Accounting code
                        </Label>
                        <Input
                            id={`${idPrefix}-gl-code`}
                            name="gl_code"
                            placeholder="4100-utilities"
                            maxLength={32}
                            defaultValue={
                                mode === 'edit'
                                    ? (editCharge?.gl_code ?? '')
                                    : ''
                            }
                        />
                        <InputError message={errors.gl_code} />
                    </CollapsibleContent>
                </Collapsible>

                {mode === 'edit' && (
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id={`${idPrefix}-is-active`}
                            checked={chargeIsActive}
                            onCheckedChange={(checked) =>
                                setChargeIsActive(checked === true)
                            }
                        />
                        <input
                            type="hidden"
                            name="is_active"
                            value={chargeIsActive ? '1' : ''}
                        />
                        <Label
                            htmlFor={`${idPrefix}-is-active`}
                            className="cursor-pointer text-sm"
                        >
                            Active — inactive charges stop billing but keep
                            their history
                        </Label>
                    </div>
                )}

                <fieldset className="space-y-3">
                    <legend className="text-sm font-medium">
                        Units that pay
                    </legend>
                    {chargeUnits.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {chargePropertyId
                                ? 'This property has no units yet.'
                                : 'Choose a property first.'}
                        </p>
                    ) : (
                        chargeUnits.map((unit) => (
                            <div key={unit.id} className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id={`${idPrefix}-unit-${unit.id}`}
                                        checked={chargeUnitIds.includes(
                                            unit.id,
                                        )}
                                        onCheckedChange={() =>
                                            toggleChargeUnit(unit.id)
                                        }
                                    />
                                    {chargeUnitIds.includes(unit.id) && (
                                        <input
                                            type="hidden"
                                            name="unit_ids[]"
                                            value={String(unit.id)}
                                        />
                                    )}
                                    <Label
                                        htmlFor={`${idPrefix}-unit-${unit.id}`}
                                        className="cursor-pointer text-sm"
                                    >
                                        {unit.name}
                                    </Label>
                                </div>
                                {needsAmounts &&
                                    chargeUnitIds.includes(unit.id) && (
                                        <div className="ml-6 grid gap-2">
                                            <Label
                                                htmlFor={`${idPrefix}-amount-${unit.id}`}
                                            >
                                                Amount for {unit.name} (
                                                {chargeCurrency})
                                            </Label>
                                            <Input
                                                id={`${idPrefix}-amount-${unit.id}`}
                                                name={`amounts[${unit.id}]`}
                                                type="number"
                                                inputMode="decimal"
                                                step="0.01"
                                                min="0"
                                                required
                                                value={
                                                    chargeAmounts[unit.id] ?? ''
                                                }
                                                onChange={(event) =>
                                                    setChargeAmounts(
                                                        (current) => ({
                                                            ...current,
                                                            [unit.id]:
                                                                event.target
                                                                    .value,
                                                        }),
                                                    )
                                                }
                                            />
                                        </div>
                                    )}
                            </div>
                        ))
                    )}
                    <InputError message={errors.unit_ids ?? errors.amounts} />
                </fieldset>
            </>
        );
    }

    function tableParams(params: Record<string, string | number>) {
        return {
            search: filters.search,
            unit_id: filters.unit,
            utility: filters.utility,
            sort: sort.column ?? '',
            direction: sort.direction,
            ...params,
        };
    }

    function refetchTable(
        params: Record<string, string | number>,
        options?: { replace?: boolean },
    ) {
        setIsTableLoading(true);

        router.get(
            utilitiesIndex.url({ query: params }),
            {},
            {
                preserveState: true,
                replace: options?.replace ?? false,
                only: ['readings', 'focusedReading'],
                onFinish: () => setIsTableLoading(false),
            },
        );
    }

    function applySorting(next: SortingState) {
        const first = next[0];

        refetchTable(
            tableParams({
                sort: first?.id ?? '',
                direction: first
                    ? first.desc
                        ? 'desc'
                        : 'asc'
                    : sort.direction,
            }),
            { replace: true },
        );
    }

    function changePage(nextPage: number) {
        refetchTable(tableParams({ page: nextPage }));
    }

    function applyFilters(params: Record<string, string | number>) {
        refetchTable(tableParams({ ...params, filters: '1' }), {
            replace: true,
        });
    }

    function clearFilters() {
        setSearchDraft('');
        refetchTable(
            tableParams({
                search: '',
                unit_id: '',
                utility: '',
                filters: '',
            }),
            { replace: true },
        );
    }

    // Live search: local input for responsiveness, debounced server round-trip.
    const [searchDraft, setSearchDraft] = useState(filters.search);
    const [serverSearch, setServerSearch] = useState(filters.search);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        if (searchDraft === filters.search) {
            return;
        }

        const timer = window.setTimeout(() => {
            refetchTable(tableParams({ search: searchDraft }), {
                replace: true,
            });
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchDraft]);

    if (filters.search !== serverSearch) {
        setServerSearch(filters.search);
        setSearchDraft(filters.search);
    }

    const sorting = useMemo<SortingState>(
        () =>
            sort.column
                ? [{ id: sort.column, desc: sort.direction === 'desc' }]
                : [],
        [sort.column, sort.direction],
    );

    /** Spoken description of the active filters, for the export sheet. */
    const activeFilterSummary =
        [
            filters.search ? `"${filters.search}"` : '',
            filters.unit
                ? `unit ${units.find((unit) => String(unit.value) === filters.unit)?.label ?? filters.unit}`
                : '',
            filters.utility
                ? `utility ${utilities.find((utility) => utility.value === filters.utility)?.label ?? filters.utility}`
                : '',
        ]
            .filter(Boolean)
            .join(' and ') || null;

    function exportParams(scope: 'all' | 'page', columns: string[]) {
        return {
            search: filters.search,
            unit_id: filters.unit,
            utility: filters.utility,
            sort: sort.column ?? '',
            direction: sort.direction,
            scope,
            columns,
            ...(scope === 'page' ? { page: readings.current_page } : {}),
        };
    }

    function runExport(scope: 'all' | 'page', columns: string[]) {
        downloadCsv(exportUtilities().url, exportParams(scope, columns));
        setOverlayParam('export', null, { replace: true });
    }

    const columns: ColumnDef<UtilityReading, unknown>[] = [
        {
            id: 'unit',
            accessorKey: 'unit_name',
            header: 'Unit',
            cell: ({ row }) => (
                <span className="font-medium">
                    {row.original.unit_name ?? '—'}
                </span>
            ),
        },
        {
            id: 'utility',
            accessorKey: 'utility',
            header: 'Utility',
            cell: ({ row }) => (
                <Badge variant="outline" className="capitalize">
                    {row.original.utility_label}
                </Badge>
            ),
        },
        {
            id: 'reading',
            accessorKey: 'reading',
            header: 'Reading',
            cell: ({ row }) => (
                <span className="tabular-nums">
                    {row.original.reading.toLocaleString()}{' '}
                    <span className="text-muted-foreground">
                        {row.original.unit_of_measure}
                    </span>
                </span>
            ),
        },
        {
            id: 'consumption',
            header: 'Consumption',
            enableSorting: false,
            cell: ({ row }) => (
                <span className="tabular-nums">
                    {row.original.consumption === null ? (
                        <span className="text-muted-foreground">—</span>
                    ) : (
                        `${row.original.consumption.toLocaleString()} ${row.original.unit_of_measure}`
                    )}
                </span>
            ),
        },
        {
            id: 'read_on',
            accessorKey: 'read_on',
            header: 'Read on',
            cell: ({ row }) => <span>{row.original.read_on}</span>,
        },
        {
            id: 'recorded_by',
            header: 'Recorded by',
            enableSorting: false,
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.recorder_name ?? '—'}
                </span>
            ),
        },
    ];

    const chargeDetail = (charge: UtilityChargeRow) => {
        if (charge.kind === 'metered') {
            return `${formatMoney(charge.rate, charge.currency)} per ${charge.unit_of_measure ?? 'unit'}`;
        }

        if (charge.kind === 'shared') {
            const divisor =
                charge.allocation === 'occupants'
                    ? 'occupants'
                    : `${charge.units.length} unit${charge.units.length === 1 ? '' : 's'}`;

            return `${formatMoney(charge.master_amount, charge.currency)} ÷ ${divisor}`;
        }

        const amounts = charge.units
            .map((unit) => Number(unit.amount ?? 0))
            .filter((amount) => amount > 0);

        if (amounts.length === 0) {
            return 'No amounts set';
        }

        const min = Math.min(...amounts);
        const max = Math.max(...amounts);
        const same =
            min === max
                ? `${min.toLocaleString()}`
                : `${min.toLocaleString()}–${max.toLocaleString()}`;

        return `${charge.currency} ${same} × ${amounts.length} unit${amounts.length === 1 ? '' : 's'}`;
    };

    const chargeColumns: ColumnDef<UtilityChargeRow, unknown>[] = [
        {
            id: 'name',
            header: 'Charge',
            cell: ({ row }) => (
                <span className="font-medium">{row.original.name}</span>
            ),
        },
        {
            id: 'property',
            header: 'Property',
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.property_name ?? '—'}
                </span>
            ),
        },
        {
            id: 'kind',
            header: 'Kind',
            cell: ({ row }) => (
                <Badge variant="outline" className="capitalize">
                    {row.original.kind_label}
                </Badge>
            ),
        },
        {
            id: 'detail',
            header: 'Amount',
            cell: ({ row }) => (
                <span className="tabular-nums">
                    {chargeDetail(row.original)}
                </span>
            ),
        },
        {
            id: 'units',
            header: 'Units',
            cell: ({ row }) => (
                <span
                    className="block max-w-48 truncate text-muted-foreground"
                    title={row.original.units
                        .map((unit) => unit.name)
                        .join(', ')}
                >
                    {row.original.units.length === 0
                        ? '—'
                        : `${row.original.units.length} unit${row.original.units.length === 1 ? '' : 's'}: ${row.original.units.map((unit) => unit.name).join(', ')}`}
                </span>
            ),
        },
        {
            id: 'status',
            header: 'Status',
            cell: ({ row }) => (
                <Badge variant={row.original.is_active ? 'success' : 'neutral'}>
                    {row.original.is_active ? 'Active' : 'Inactive'}
                </Badge>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            enableSorting: false,
            meta: { align: 'right' },
            cell: ({ row }) => {
                const charge = row.original;

                return (
                    <div
                        className="flex justify-end gap-1"
                        onClick={(event) => event.stopPropagation()}
                    >
                        {canManageCharges && (
                            <ActionIconButton
                                icon={<SquarePen />}
                                label={`Edit ${charge.name}`}
                                color="edit"
                                onClick={() => openChargeEdit(charge)}
                            />
                        )}
                        {canManageCharges && (
                            <ActionIconButton
                                icon={<Trash2 />}
                                label={`Delete ${charge.name}`}
                                color="danger"
                                onClick={() => setDeleteCharge(charge)}
                            />
                        )}
                    </div>
                );
            },
        },
    ];

    return (
        <>
            <Head title="Utilities" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        className="min-w-0 flex-1"
                        level="h1"
                        title="Utilities"
                        description="Record utility consumption for a unit. Consumption is calculated from the previous reading."
                    />
                    <div className="ml-auto flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={openBulkReadings}
                        >
                            <ClipboardList className="size-4" />
                            Record many
                        </Button>
                        <LabelledIconButton
                            icon={<Plus />}
                            label="Record reading"
                            tooltip="Record a utility reading"
                            color="primary"
                            onClick={() => setOverlayParam('create', '1')}
                            className="max-w-20 sm:max-w-24"
                        />
                    </div>
                </div>

                {setup.some(
                    (property) =>
                        property.units_without_rent > 0 ||
                        property.active_charges === 0 ||
                        property.latest_reading_on === null,
                ) && (
                    <Card className="gap-4 p-4 sm:p-5">
                        <div>
                            <h2 className="font-medium">
                                Finish setting up billing
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Three steps per property. Once everything ticks,
                                this card goes away.
                            </p>
                        </div>
                        <div className="grid gap-4">
                            {setup
                                .filter(
                                    (property) =>
                                        property.units_without_rent > 0 ||
                                        property.active_charges === 0 ||
                                        property.latest_reading_on === null,
                                )
                                .map((property) => (
                                    <div
                                        key={property.id}
                                        className="grid gap-2"
                                    >
                                        <p className="text-sm font-medium">
                                            {property.name}
                                        </p>
                                        <ul className="grid gap-2">
                                            <li className="flex items-center gap-2 text-sm">
                                                {property.units_without_rent ===
                                                0 ? (
                                                    <Check className="size-4 shrink-0 text-status-success-fg" />
                                                ) : (
                                                    <Circle className="size-4 shrink-0 text-muted-foreground" />
                                                )}
                                                <span className="min-w-0 flex-1 text-muted-foreground">
                                                    {property.units_without_rent ===
                                                    0 ? (
                                                        <>
                                                            Rent set on all{' '}
                                                            {
                                                                property.units_count
                                                            }{' '}
                                                            unit
                                                            {property.units_count ===
                                                            1
                                                                ? ''
                                                                : 's'}
                                                        </>
                                                    ) : (
                                                        <>
                                                            Set rent on{' '}
                                                            {
                                                                property.units_without_rent
                                                            }{' '}
                                                            of{' '}
                                                            {
                                                                property.units_count
                                                            }{' '}
                                                            units
                                                        </>
                                                    )}
                                                </span>
                                                {property.units_without_rent >
                                                    0 && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.visit(
                                                                unitsIndex.url({
                                                                    query: {
                                                                        property:
                                                                            String(
                                                                                property.id,
                                                                            ),
                                                                    },
                                                                }),
                                                            )
                                                        }
                                                    >
                                                        Set rents
                                                    </Button>
                                                )}
                                            </li>
                                            <li className="flex items-center gap-2 text-sm">
                                                {property.active_charges > 0 ? (
                                                    <Check className="size-4 shrink-0 text-status-success-fg" />
                                                ) : (
                                                    <Circle className="size-4 shrink-0 text-muted-foreground" />
                                                )}
                                                <span className="min-w-0 flex-1 text-muted-foreground">
                                                    {property.active_charges >
                                                    0 ? (
                                                        <>
                                                            {
                                                                property.active_charges
                                                            }{' '}
                                                            active charge
                                                            {property.active_charges ===
                                                            1
                                                                ? ''
                                                                : 's'}
                                                        </>
                                                    ) : (
                                                        'No utility charges yet'
                                                    )}
                                                </span>
                                                {property.active_charges ===
                                                    0 &&
                                                    canManageCharges && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                openChargeCreate(
                                                                    property.id,
                                                                )
                                                            }
                                                        >
                                                            Add charge
                                                        </Button>
                                                    )}
                                            </li>
                                            <li className="flex items-center gap-2 text-sm">
                                                {property.latest_reading_on !==
                                                null ? (
                                                    <Check className="size-4 shrink-0 text-status-success-fg" />
                                                ) : (
                                                    <Circle className="size-4 shrink-0 text-muted-foreground" />
                                                )}
                                                <span className="min-w-0 flex-1 text-muted-foreground">
                                                    {property.latest_reading_on !==
                                                    null ? (
                                                        <>
                                                            Last reading on{' '}
                                                            {
                                                                property.latest_reading_on
                                                            }
                                                        </>
                                                    ) : (
                                                        'No readings recorded yet'
                                                    )}
                                                </span>
                                                {property.latest_reading_on ===
                                                    null && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setOverlayParam(
                                                                'create',
                                                                '1',
                                                            )
                                                        }
                                                    >
                                                        Record reading
                                                    </Button>
                                                )}
                                            </li>
                                        </ul>
                                    </div>
                                ))}
                        </div>
                    </Card>
                )}

                <Card className="gap-0 py-0">
                    <div className="flex flex-wrap items-center gap-2 border-b border-border-strong p-4">
                        <div className="relative min-w-56 flex-1">
                            <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={searchDraft}
                                onChange={(event) =>
                                    setSearchDraft(event.target.value)
                                }
                                placeholder="Search units..."
                                aria-label="Search utilities"
                                className="border-border-strong bg-background pr-3 pl-9"
                            />
                        </div>
                        <div className="ml-auto hidden items-center gap-2 sm:flex">
                            <ActionIconButton
                                icon={<ListFilter />}
                                label="Filters"
                                color="teal"
                                className="coarse:min-h-9 coarse:min-w-9"
                                onClick={() =>
                                    isFiltersOpen
                                        ? setOverlayParam('filters', null, {
                                              replace: true,
                                          })
                                        : setOverlayParam('filters', '1')
                                }
                            />
                            <ActionIconButton
                                icon={<Download />}
                                label="Export"
                                color="teal"
                                className="coarse:min-h-9 coarse:min-w-9"
                                onClick={() => setOverlayParam('export', '1')}
                            />
                        </div>
                        <ActionIconButton
                            icon={<MoreHorizontal />}
                            label="More actions"
                            color="teal"
                            className="ml-auto sm:hidden coarse:min-h-9 coarse:min-w-9"
                            onClick={() => setIsMoreOpen(true)}
                        />
                    </div>

                    <DataTable
                        columns={columns}
                        data={readings.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        onSortingChange={applySorting}
                        loading={isTableLoading}
                        onRowClick={(reading) => openView(reading)}
                        empty="No readings match your filters."
                    />
                </Card>

                <Pagination
                    currentPage={readings.current_page}
                    lastPage={readings.last_page}
                    total={readings.total}
                    label="readings"
                    onChange={changePage}
                />

                <Card className="gap-0 py-0">
                    <div className="flex flex-wrap items-center gap-2 border-b border-border-strong p-4">
                        <div className="min-w-0 flex-1">
                            <h2 className="font-medium">Utility charges</h2>
                            <p className="text-sm text-muted-foreground">
                                Metered rates, fixed and shared bills — and
                                which units pay them. Single fees live on each
                                lease instead.
                            </p>
                        </div>
                        {canManageCharges && (
                            <LabelledIconButton
                                icon={<Plus />}
                                label="Add charge"
                                tooltip="Add a utility charge"
                                color="primary"
                                onClick={openChargeCreate}
                                className="ml-auto max-w-20 sm:max-w-24"
                            />
                        )}
                    </div>

                    <DataTable
                        columns={chargeColumns}
                        data={charges}
                        getRowId={(row) => String(row.id)}
                        empty="No utility charges yet."
                    />
                </Card>
            </div>

            <TableOverflowMenu
                open={isMoreOpen}
                onOpenChange={setIsMoreOpen}
                actions={[
                    {
                        icon: <ListFilter />,
                        label: 'Filters',
                        onSelect: () => setOverlayParam('filters', '1'),
                    },
                    {
                        icon: <Download />,
                        label: 'Export',
                        onSelect: () => setOverlayParam('export', '1'),
                    },
                ]}
            />

            <ResponsiveOverlay
                open={isFiltersOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('filters', null, { replace: true });
                    }
                }}
                title="Filters"
                description="Narrow the list of readings."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('filters', null, { replace: true })
                        }
                        cancelLabel="Close"
                        actions={[
                            {
                                label: 'Clear all',
                                onClick: () => {
                                    clearFilters();
                                    setOverlayParam('filters', null, {
                                        replace: true,
                                    });
                                },
                            },
                        ]}
                    />
                }
            >
                <div className="grid gap-4 py-2">
                    <div className="grid gap-2">
                        <Label htmlFor="utility-filter-unit">Unit</Label>
                        <SearchableSelect
                            id="utility-filter-unit"
                            name="utility_filter_unit"
                            options={[
                                'All units',
                                ...units.map((unit) => ({
                                    value: String(unit.value),
                                    label: unit.label,
                                })),
                            ]}
                            value={filters.unit || 'All units'}
                            onValueChange={(value) =>
                                applyFilters({
                                    unit_id: value === 'All units' ? '' : value,
                                })
                            }
                            placeholder="All units"
                            searchPlaceholder="Search units…"
                        />
                    </div>

                    <fieldset className="space-y-3">
                        <legend className="text-sm font-medium">Utility</legend>
                        <RadioGroup
                            value={filters.utility || 'all'}
                            onValueChange={(value) =>
                                applyFilters({
                                    utility: value === 'all' ? '' : value,
                                })
                            }
                            className="space-y-3"
                        >
                            {[
                                { value: 'all', label: 'Any utility' },
                                ...utilities.map((utility) => ({
                                    value: utility.value,
                                    label: `${utility.label} (${utility.unit_of_measure})`,
                                })),
                            ].map((option) => (
                                <div
                                    key={option.value}
                                    className="flex items-center gap-2"
                                >
                                    <RadioGroupItem
                                        id={`utility-filter-${option.value}`}
                                        value={option.value}
                                    />
                                    <Label
                                        htmlFor={`utility-filter-${option.value}`}
                                        className="cursor-pointer"
                                    >
                                        {option.label}
                                    </Label>
                                </div>
                            ))}
                        </RadioGroup>
                    </fieldset>
                </div>
            </ResponsiveOverlay>

            <CsvExportSheet
                open={isExportOpen}
                onClose={() =>
                    setOverlayParam('export', null, { replace: true })
                }
                canExport
                total={readings.total}
                pageCount={readings.data.length}
                currentPage={readings.current_page}
                activeFilters={activeFilterSummary}
                columns={[
                    'ID',
                    'Unit',
                    'Utility',
                    'Reading',
                    'Unit of measure',
                    'Consumption',
                    'Read on',
                    'Recorded by',
                ]}
                onExport={runExport}
            />

            <ResponsiveOverlay
                open={isBulkOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        closeBulkReadings();
                    }
                }}
                title="Record many readings"
                description="One walked route: fill every meter you read, leave the rest blank. Only filled rows are saved."
                footer={
                    <OverlayFooter
                        onCancel={closeBulkReadings}
                        actions={[
                            {
                                label: `Save ${filledGridRows.length === 1 ? 'reading' : 'readings'}`,
                                form: 'utility-bulk-reading-form',
                                type: 'submit',
                                disabled: filledGridRows.length === 0,
                            },
                        ]}
                    />
                }
            >
                <Form
                    id="utility-bulk-reading-form"
                    {...bulkStoreUtilityReadings.form()}
                    options={{ preserveScroll: true, preserveState: true }}
                    onSuccess={closeBulkReadings}
                    className="grid gap-4 py-2"
                >
                    {({ errors }) => (
                        <>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="bulk-grid-property">
                                        Property
                                    </Label>
                                    <SearchableSelect
                                        id="bulk-grid-property"
                                        name="grid_property"
                                        options={properties.map((property) => ({
                                            value: String(property.id),
                                            label: property.name,
                                        }))}
                                        value={gridPropertyId}
                                        onValueChange={pickGridProperty}
                                        placeholder="Choose a property"
                                        searchPlaceholder="Search properties…"
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="bulk-grid-read-on">
                                        Read on
                                    </Label>
                                    <Input
                                        id="bulk-grid-read-on"
                                        type="date"
                                        max={new Date()
                                            .toISOString()
                                            .slice(0, 10)}
                                        value={gridReadOn}
                                        onChange={(event) =>
                                            setGridReadOn(event.target.value)
                                        }
                                    />
                                </div>
                            </div>
                            <InputError message={errors.readings} />
                            {gridRows.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No metered charges assigned in this building
                                    yet — add a water or electricity charge
                                    first, then come back to walk the route.
                                </p>
                            ) : (
                                <div className="grid gap-2">
                                    {gridRows.map((row) => {
                                        const key = `${row.unit_id}:${row.utility}`;
                                        const index = filledGridRows.findIndex(
                                            (filled) =>
                                                `${filled.unit_id}:${filled.utility}` ===
                                                key,
                                        );
                                        const posted = index !== -1;

                                        return (
                                            <div
                                                key={key}
                                                className="grid gap-1 rounded-lg border border-border-strong p-3"
                                            >
                                                <div className="flex items-baseline justify-between gap-2">
                                                    <span className="text-sm font-medium">
                                                        {row.unit_name} ·{' '}
                                                        {row.utility_label}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {row.rate !== null
                                                            ? `${formatMoney(row.rate, row.currency)} per ${row.unit_of_measure}`
                                                            : 'No rate set'}
                                                    </span>
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {row.last_reading !== null
                                                        ? `Last: ${Number(row.last_reading).toLocaleString()} ${row.unit_of_measure} on ${row.last_read_on}`
                                                        : 'No readings yet — this one starts the history.'}
                                                </p>
                                                <div className="grid grid-cols-2 gap-2">
                                                    <Input
                                                        id={`bulk-reading-${key}`}
                                                        type="number"
                                                        inputMode="decimal"
                                                        step="0.001"
                                                        min="0"
                                                        placeholder="New reading"
                                                        aria-label={`New ${row.utility_label} reading for ${row.unit_name}`}
                                                        value={
                                                            gridValues[key] ??
                                                            ''
                                                        }
                                                        onChange={(event) =>
                                                            setGridValues(
                                                                (current) => ({
                                                                    ...current,
                                                                    [key]: event
                                                                        .target
                                                                        .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                    {posted && (
                                                        <>
                                                            <input
                                                                type="hidden"
                                                                name={`readings[${index}][unit_id]`}
                                                                value={
                                                                    row.unit_id
                                                                }
                                                            />
                                                            <input
                                                                type="hidden"
                                                                name={`readings[${index}][utility]`}
                                                                value={
                                                                    row.utility
                                                                }
                                                            />
                                                            <input
                                                                type="hidden"
                                                                name={`readings[${index}][reading]`}
                                                                value={
                                                                    gridValues[
                                                                        key
                                                                    ] ?? ''
                                                                }
                                                            />
                                                            <input
                                                                type="hidden"
                                                                name={`readings[${index}][read_on]`}
                                                                value={
                                                                    gridReadOn
                                                                }
                                                            />
                                                        </>
                                                    )}
                                                </div>
                                                <InputError
                                                    message={
                                                        errors[
                                                            `readings.${index}.reading`
                                                        ] ??
                                                        errors[
                                                            `readings.${index}.read_on`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </>
                    )}
                </Form>
            </ResponsiveOverlay>

            <ResponsiveOverlay
                open={isCreateOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setOverlayParam('create', null, { replace: true });
                    }
                }}
                title="Record a reading"
                description="One reading per unit, utility and day."
                footer={
                    <OverlayFooter
                        onCancel={() =>
                            setOverlayParam('create', null, { replace: true })
                        }
                        actions={[
                            {
                                label: 'Save reading',
                                form: 'utility-reading-form',
                                type: 'submit',
                            },
                        ]}
                    />
                }
            >
                <Form
                    id="utility-reading-form"
                    {...storeUtilityReading.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setCreateUtility('');
                        setOverlayParam('create', null, { replace: true });
                    }}
                    className="grid gap-4 py-2"
                >
                    {({ errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="reading-unit">Unit</Label>
                                <SearchableSelect
                                    id="reading-unit"
                                    name="unit_id"
                                    options={units.map((unit) => ({
                                        value: String(unit.value),
                                        label: unit.label,
                                    }))}
                                    placeholder="Choose a unit"
                                    searchPlaceholder="Search units…"
                                />
                                <InputError message={errors.unit_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="reading-utility">Utility</Label>
                                <input
                                    type="hidden"
                                    name="utility"
                                    value={createUtility}
                                />
                                <Select
                                    value={createUtility}
                                    onValueChange={setCreateUtility}
                                >
                                    <SelectTrigger
                                        id="reading-utility"
                                        className="w-full"
                                        aria-invalid={!!errors.utility}
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
                                <InputError message={errors.utility} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="reading-value">
                                        Reading
                                    </Label>
                                    <Input
                                        id="reading-value"
                                        name="reading"
                                        type="number"
                                        inputMode="decimal"
                                        step="0.001"
                                        min="0"
                                    />
                                    <InputError message={errors.reading} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="reading-read-on">
                                        Read on
                                    </Label>
                                    <Input
                                        id="reading-read-on"
                                        name="read_on"
                                        type="date"
                                        max={new Date()
                                            .toISOString()
                                            .slice(0, 10)}
                                        defaultValue={new Date()
                                            .toISOString()
                                            .slice(0, 10)}
                                    />
                                    <InputError message={errors.read_on} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="reading-notes">
                                    Notes{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <Textarea
                                    id="reading-notes"
                                    name="notes"
                                    rows={2}
                                />
                                <InputError message={errors.notes} />
                            </div>
                        </>
                    )}
                </Form>
            </ResponsiveOverlay>

            {viewedReading && (
                <ResponsiveOverlay
                    open={isViewOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeView();
                        }
                    }}
                    title={`Reading ${viewedReading.reading.toLocaleString()} ${viewedReading.unit_of_measure}`}
                    description={`${viewedReading.utility_label} · ${viewedReading.unit_name ?? 'No unit'} · ${viewedReading.read_on}`}
                    footer={<OverlayFooter onCancel={closeView} />}
                >
                    <dl className="grid gap-x-6 gap-y-3 py-2 text-sm">
                        {(
                            [
                                ['Unit', viewedReading.unit_name ?? '—'],
                                ['Utility', viewedReading.utility_label],
                                [
                                    'Reading',
                                    `${viewedReading.reading.toLocaleString()} ${viewedReading.unit_of_measure}`,
                                ],
                                [
                                    'Consumption',
                                    viewedReading.consumption === null
                                        ? 'First reading — consumption starts from the next one'
                                        : `${viewedReading.consumption.toLocaleString()} ${viewedReading.unit_of_measure}`,
                                ],
                                ['Read on', viewedReading.read_on],
                                [
                                    'Recorded by',
                                    viewedReading.recorder_name ?? '—',
                                ],
                                ['Notes', viewedReading.notes || '—'],
                            ] as [string, string][]
                        ).map(([label, value]) => (
                            <div
                                key={label}
                                className="grid grid-cols-[8rem_1fr] gap-2"
                            >
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="min-w-0 break-words">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </ResponsiveOverlay>
            )}
            {canManageCharges && (
                <ResponsiveOverlay
                    open={isChargeCreateOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeChargeSheets();
                        }
                    }}
                    title="Add a utility charge"
                    description="Metered rates, fixed and shared bills charged to the units that pay them. Single fees live on each lease instead."
                    footer={
                        <OverlayFooter
                            onCancel={closeChargeSheets}
                            actions={[
                                {
                                    label: 'Add charge',
                                    form: 'utility-charge-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    }
                >
                    <Form
                        key="charge-new"
                        id="utility-charge-form"
                        {...storeUtilityCharge.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => closeChargeSheets()}
                        className="grid gap-4 py-2"
                    >
                        {({ errors }) =>
                            chargeFields(errors, 'charge-new', 'create')
                        }
                    </Form>
                </ResponsiveOverlay>
            )}

            {canManageCharges && editCharge && (
                <ResponsiveOverlay
                    open={isChargeEditOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeChargeSheets();
                        }
                    }}
                    title={`Edit ${editCharge.name}`}
                    description="Reprice, reassign units, or pause billing."
                    footer={
                        <OverlayFooter
                            onCancel={closeChargeSheets}
                            actions={[
                                {
                                    label: 'Save changes',
                                    form: 'utility-charge-edit-form',
                                    type: 'submit',
                                },
                            ]}
                        />
                    }
                >
                    <Form
                        key={editCharge.id}
                        id="utility-charge-edit-form"
                        {...updateUtilityCharge.form({
                            charge: editCharge.id,
                        })}
                        options={{ preserveScroll: true }}
                        onSuccess={() => closeChargeSheets()}
                        className="grid gap-4 py-2"
                    >
                        {({ errors }) =>
                            chargeFields(errors, 'charge-edit', 'edit')
                        }
                    </Form>
                </ResponsiveOverlay>
            )}

            {canManageCharges && deleteCharge !== null && (
                <ConfirmDeleteOverlay
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setDeleteCharge(null);
                        }
                    }}
                    action={destroyUtilityCharge(deleteCharge.id).url}
                    title={`Delete ${deleteCharge.name}?`}
                    description="This removes the charge and its unit assignments. Readings are kept, and future totals stop listing it."
                    onSuccess={() => setDeleteCharge(null)}
                />
            )}
        </>
    );
}

UtilitiesIndex.layout = {
    breadcrumbs: [{ title: 'Utilities' }],
};
