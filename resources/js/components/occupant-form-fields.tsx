import { Check, Plus, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import DocumentBuilder from '@/components/document-builder';
import { FrequencySelect } from '@/components/frequency-select';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import type { TemplateToken } from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Unit = {
    id: number;
    name: string;
    type: string | null;
    status: string;
    rent_amount: string | number | null;
    rent_frequency: string | null;
};

type AgreementTemplateOption = {
    id: number;
    name: string;
    body_html: string;
};

type GroupedTemplates = {
    property: AgreementTemplateOption[];
    organization: AgreementTemplateOption[];
};

type LeaseDefaults = {
    starts_at?: string | null;
    rent_amount?: number | string | null;
    rent_frequency?: string | null;
    rent_due_day?: number | string | null;
    rent_due_weekday?: number | string | null;
    rent_due_month?: number | string | null;
    deposit?: number | null;
    currency?: string | null;
    agreement_text?: string | null;
    agreement_document_name?: string | null;
    agreement_document_url?: string | null;
};

type Defaults = {
    first_name?: string;
    last_name?: string | null;
    email?: string | null;
    phone?: string | null;
    national_id?: string | null;
    gender?: string | null;
    status?: string;
    unit_ids?: number[];
    lease?: LeaseDefaults | null;
};

type Props = {
    units: Unit[];
    templates: GroupedTemplates;
    availableTokens?: TemplateToken[];
    errors: Record<string, string>;
    idPrefix: string;
    propertyName: string;
    leaseSectionHint?: string;
    defaults?: Defaults;
    /**
     * Create mode: the move-in payment section replaces the status picker
     * (the server derives the status from it). Edit mode keeps the status
     * picker and omits the section.
     */
    showMoveIn?: boolean;
    /**
     * Reports whether the sheet may submit: no local field errors and at
     * least one unit selected. The page disables the footer submit while
     * false; the server still validates everything on submit.
     */
    onValidityChange?: (valid: boolean) => void;
};

const WEEKDAYS = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
];
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
const CURRENCIES = ['KES', 'USD', 'EUR', 'GBP'];
const STATUSES = ['active', 'inactive', 'moved_out'];

/**
 * Identity, lease, agreement and unit fields shared by the occupant create
 * and edit sheets. Controlled where the UI must stay in sync (selects,
 * toggles, agreement editor); everything else posts as plain form data.
 * Callers must `key` the sheet content by record so switching rows resets
 * the state.
 */
export default function OccupantFormFields({
    units,
    templates,
    availableTokens,
    errors,
    idPrefix,
    propertyName,
    leaseSectionHint = "Capture the rental terms. These power the renter's rental history.",
    defaults = {},
    showMoveIn = false,
    onValidityChange,
}: Props) {
    const templateList = [...templates.property, ...templates.organization];
    const [selectedTemplateId, setSelectedTemplateId] = useState('');
    const [status, setStatus] = useState(defaults.status ?? 'active');
    const [moveInNow, setMoveInNow] = useState(true);
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [unitIds, setUnitIds] = useState<number[]>(defaults.unit_ids ?? []);
    const [gender, setGender] = useState(defaults.gender ?? 'unspecified');
    const [unitQuery, setUnitQuery] = useState('');
    const [unitPickerOpen, setUnitPickerOpen] = useState(false);
    const firstFieldRef = useRef<HTMLInputElement | null>(null);

    useEffect(() => {
        // The agreement editor focuses itself when it mounts (TipTap applies
        // the initial content through selection updates, which focus an
        // unfocused view) — and it mounts lazily, after this effect runs,
        // stealing focus twice in quick succession. Redirect each programmatic
        // steal back to the first field. A focus that immediately follows a
        // pointer/keyboard gesture is the user choosing the editor, so those
        // are left alone. Time-boxed to the open transition.
        firstFieldRef.current?.focus();

        let lastGesture = 0;
        const markGesture = () => {
            lastGesture = Date.now();
        };
        const redirectSteal = (event: FocusEvent) => {
            if (Date.now() - lastGesture < 350) {
                return;
            }

            const target = event.target as HTMLElement | null;

            if (target?.closest?.('.ProseMirror')) {
                firstFieldRef.current?.focus();
            }
        };

        document.addEventListener('pointerdown', markGesture);
        document.addEventListener('keydown', markGesture);
        document.addEventListener('focusin', redirectSteal);
        const timer = window.setTimeout(() => {
            document.removeEventListener('pointerdown', markGesture);
            document.removeEventListener('keydown', markGesture);
            document.removeEventListener('focusin', redirectSteal);
        }, 2000);

        return () => {
            document.removeEventListener('pointerdown', markGesture);
            document.removeEventListener('keydown', markGesture);
            document.removeEventListener('focusin', redirectSteal);
            window.clearTimeout(timer);
        };
    }, []);

    const matchingUnits = useMemo(() => {
        const needle = unitQuery.trim().toLowerCase();

        if (needle === '') {
            return units;
        }

        return units.filter((unit) =>
            `${unit.name} ${unit.type ?? ''}`.toLowerCase().includes(needle),
        );
    }, [units, unitQuery]);

    function toggleUnit(id: number) {
        setUnitIds((current) =>
            current.includes(id)
                ? current.filter((uid) => uid !== id)
                : [...current, id],
        );
    }

    // Live validation mirrors the server rules so the obvious mistakes
    // surface on blur instead of after a round trip. Submit stays disabled
    // while anything here fails; the server remains the authority.
    type FieldErrors = Partial<
        Record<'first_name' | 'email' | 'lease.rent_due_day', string>
    >;

    const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

    useEffect(() => {
        onValidityChange?.(
            Object.keys(fieldErrors).length === 0 && unitIds.length > 0,
        );
    }, [fieldErrors, unitIds, onValidityChange]);

    function validateField(
        name: 'first_name' | 'email' | 'lease.rent_due_day',
        value: string,
    ): string | null {
        if (name === 'first_name') {
            return value.trim() === '' ? 'First name is required.' : null;
        }

        if (name === 'email') {
            if (value.trim() === '') {
                return 'Email address is required.';
            }

            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())
                ? null
                : 'Enter a valid email address.';
        }

        // Optional, but when given it must be a calendar day.
        if (value.trim() === '') {
            return null;
        }

        const day = Number(value);

        return Number.isInteger(day) && day >= 1 && day <= 31
            ? null
            : 'Enter a day between 1 and 31.';
    }

    function setFieldError(
        name: 'first_name' | 'email' | 'lease.rent_due_day',
        message: string | null,
    ) {
        setFieldErrors((current) => {
            if (message === null) {
                if (!(name in current)) {
                    return current;
                }

                const rest = { ...current };
                delete rest[name];

                return rest;
            }

            if (current[name] === message) {
                return current;
            }

            return { ...current, [name]: message };
        });
    }
    const [rentFrequency, setRentFrequency] = useState(
        defaults.lease?.rent_frequency ?? 'monthly',
    );
    const [rentAmount, setRentAmount] = useState(
        defaults.lease?.rent_amount != null
            ? String(defaults.lease.rent_amount)
            : '',
    );
    const [rentDueDay, setRentDueDay] = useState(
        defaults.lease?.rent_due_day != null
            ? String(defaults.lease.rent_due_day)
            : '',
    );
    const [rentDueWeekday, setRentDueWeekday] = useState(
        defaults.lease?.rent_due_weekday != null
            ? String(defaults.lease.rent_due_weekday)
            : '',
    );
    const [rentDueMonth, setRentDueMonth] = useState(
        defaults.lease?.rent_due_month != null
            ? String(defaults.lease.rent_due_month)
            : '',
    );

    // Move-in extras drafted with the lease: parking, pets and one-time
    // fees the tenant sees before signing. Plain inputs post directly;
    // only the kind picker needs state.
    type DraftExtra = {
        name: string;
        kind: 'fixed' | 'one_time';
        amount: string;
    };
    const [extras, setExtras] = useState<DraftExtra[]>([]);

    function addExtra() {
        setExtras((current) => [
            ...current,
            { name: '', kind: 'fixed', amount: '' },
        ]);
    }

    function updateExtra(index: number, patch: Partial<DraftExtra>) {
        setExtras((current) =>
            current.map((extra, i) =>
                i === index ? { ...extra, ...patch } : extra,
            ),
        );
    }

    function removeExtra(index: number) {
        setExtras((current) => current.filter((_, i) => i !== index));
    }

    // Prefill billing from the allocated unit when a fresh lease is being
    // drafted (no lease defaults yet): one unit, no ambiguity. Anything the
    // landlord then edits stays edited — this only fires on selection
    // change. Editing an occupant never reaches here, because existing
    // leases keep their own terms.
    const hasLeaseDefaults = defaults.lease != null;
    // Assigning a fresh unit unlocks the section below: the terms then
    // draft that unit's booking, while the booked lease stays untouched.
    const addingUnits = unitIds.some(
        (id) => !(defaults.unit_ids ?? []).includes(id),
    );
    // A present lease owns its terms: the update path only creates leases
    // for newly assigned units, so edited money below would be silently
    // discarded. Locked inputs show the agreed terms for context; the rent
    // itself moves through a dated amendment on the lease record.
    const termsLocked = hasLeaseDefaults && !addingUnits;
    useEffect(() => {
        if (hasLeaseDefaults || unitIds.length !== 1) {
            return;
        }

        const picked = units.find((unit) => unit.id === unitIds[0]);

        if (!picked) {
            return;
        }

        // eslint-disable-next-line react-hooks/set-state-in-effect
        setRentFrequency(picked.rent_frequency ?? 'monthly');

        if (picked.rent_amount != null) {
            setRentAmount(String(picked.rent_amount));
        }
    }, [unitIds, hasLeaseDefaults, units]);
    const [currency, setCurrency] = useState(defaults.lease?.currency ?? 'KES');
    const [agreementText, setAgreementText] = useState(
        defaults.lease?.agreement_text ?? '<p></p>',
    );

    const applyTemplate = (id: string) => {
        setSelectedTemplateId(id);

        if (!id) {
            return;
        }

        const template = templateList.find((t) => String(t.id) === id);

        if (template) {
            setAgreementText(template.body_html);
        }
    };

    const lease = defaults.lease ?? {};

    return (
        <>
            <div className="grid gap-6 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-first_name`}>First name</Label>
                    <Input
                        id={`${idPrefix}-first_name`}
                        name="first_name"
                        type="text"
                        required
                        autoComplete="off"
                        placeholder="Jane"
                        ref={firstFieldRef}
                        defaultValue={defaults.first_name ?? ''}
                        onBlur={(event) => {
                            // The agreement editor steals focus on mount (see
                            // the effect above); that programmatic blur is not
                            // the user leaving the field, so don't flag it.
                            if (
                                (
                                    event.relatedTarget as HTMLElement | null
                                )?.closest?.('.ProseMirror')
                            ) {
                                return;
                            }

                            setFieldError(
                                'first_name',
                                validateField('first_name', event.target.value),
                            );
                        }}
                        onChange={() => setFieldError('first_name', null)}
                    />
                    <InputError
                        message={errors.first_name ?? fieldErrors.first_name}
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-last_name`}>
                        Last name (optional)
                    </Label>
                    <Input
                        id={`${idPrefix}-last_name`}
                        name="last_name"
                        type="text"
                        autoComplete="off"
                        placeholder="Wanjiru"
                        defaultValue={defaults.last_name ?? ''}
                    />
                    <InputError message={errors.last_name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-email`}>Email address</Label>
                    <Input
                        id={`${idPrefix}-email`}
                        name="email"
                        type="email"
                        required
                        autoComplete="off"
                        placeholder="jane@example.com"
                        defaultValue={defaults.email ?? ''}
                        onBlur={(event) =>
                            setFieldError(
                                'email',
                                validateField('email', event.target.value),
                            )
                        }
                        onChange={() => setFieldError('email', null)}
                    />
                    <InputError message={errors.email ?? fieldErrors.email} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-phone`}>
                        Phone (optional)
                    </Label>
                    <Input
                        id={`${idPrefix}-phone`}
                        name="phone"
                        type="tel"
                        autoComplete="off"
                        placeholder="+254 700 000 000"
                        defaultValue={defaults.phone ?? ''}
                    />
                    <InputError message={errors.phone} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-national_id`}>
                        National ID (optional)
                    </Label>
                    <Input
                        id={`${idPrefix}-national_id`}
                        name="national_id"
                        type="text"
                        autoComplete="off"
                        placeholder="12345678"
                        defaultValue={defaults.national_id ?? ''}
                    />
                    <InputError message={errors.national_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}-gender`}>Gender</Label>
                    <input
                        type="hidden"
                        name="gender"
                        value={gender === 'unspecified' ? '' : gender}
                    />
                    <Select value={gender} onValueChange={setGender}>
                        <SelectTrigger
                            id={`${idPrefix}-gender`}
                            className="w-full"
                        >
                            <SelectValue placeholder="Select" />
                        </SelectTrigger>
                        <SelectContent>
                            {[
                                {
                                    value: 'unspecified',
                                    label: 'Not specified',
                                },
                                { value: 'male', label: 'Male' },
                                { value: 'female', label: 'Female' },
                            ].map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.gender} />
                </div>

                {showMoveIn ? (
                    <input
                        type="hidden"
                        name="status"
                        value={moveInNow ? 'active' : 'inactive'}
                    />
                ) : (
                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}-status`}>Status</Label>
                        <input type="hidden" name="status" value={status} />
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger
                                id={`${idPrefix}-status`}
                                className="w-full"
                            >
                                <SelectValue placeholder="Select a status" />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUSES.map((value) => (
                                    <SelectItem key={value} value={value}>
                                        {value === 'moved_out'
                                            ? 'Moved out'
                                            : value.charAt(0).toUpperCase() +
                                              value.slice(1)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status} />
                    </div>
                )}
            </div>

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Lease details"
                    description={leaseSectionHint}
                />
                {termsLocked && (
                    <p className="text-xs text-muted-foreground">
                        These terms were fixed when the lease was booked and are
                        shown for context — saving leaves them untouched. Amend
                        the rent from the lease record.
                    </p>
                )}
                <div className="grid gap-6 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}-lease-starts_at`}>
                            Lease start date
                        </Label>
                        <Input
                            id={`${idPrefix}-lease-starts_at`}
                            name="lease[starts_at]"
                            type="date"
                            defaultValue={lease.starts_at ?? ''}
                            disabled={termsLocked}
                        />
                        <InputError message={errors['lease.starts_at']} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}-lease-rent_frequency`}>
                            Rent frequency
                        </Label>
                        <FrequencySelect
                            id={`${idPrefix}-lease-rent_frequency`}
                            name="lease[rent_frequency]"
                            value={rentFrequency}
                            onValueChange={setRentFrequency}
                            disabled={termsLocked}
                        />
                        <InputError message={errors['lease.rent_frequency']} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}-lease-rent_amount`}>
                            Rent amount
                        </Label>
                        <Input
                            id={`${idPrefix}-lease-rent_amount`}
                            name="lease[rent_amount]"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="25000"
                            value={rentAmount}
                            onChange={(event) =>
                                setRentAmount(event.target.value)
                            }
                            disabled={termsLocked}
                        />
                        <p className="text-xs text-muted-foreground">
                            Prefilled from the unit — per{' '}
                            {rentFrequency === 'daily'
                                ? 'day'
                                : rentFrequency === 'weekly'
                                  ? 'week'
                                  : rentFrequency === 'yearly'
                                    ? 'year'
                                    : 'month'}
                            . {termsLocked ? 'Locked.' : 'Editable.'}
                        </p>
                        <InputError message={errors['lease.rent_amount']} />
                    </div>

                    {rentFrequency === 'daily' ? (
                        <div className="grid gap-2">
                            <Label>Rent due</Label>
                            <p className="text-sm text-muted-foreground">
                                Daily rent accrues every day — no due date to
                                choose.
                            </p>
                        </div>
                    ) : rentFrequency === 'weekly' ? (
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`${idPrefix}-lease-rent_due_weekday`}
                            >
                                Rent due weekday
                            </Label>
                            <input
                                type="hidden"
                                name="lease[rent_due_weekday]"
                                value={rentDueWeekday}
                            />
                            <Select
                                value={rentDueWeekday}
                                onValueChange={setRentDueWeekday}
                                disabled={termsLocked}
                            >
                                <SelectTrigger
                                    id={`${idPrefix}-lease-rent_due_weekday`}
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Select weekday" />
                                </SelectTrigger>
                                <SelectContent>
                                    {WEEKDAYS.map((day, index) => (
                                        <SelectItem
                                            key={day}
                                            value={String(index)}
                                        >
                                            {day}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                The weekday each week&apos;s rent falls due.
                                Empty means the lease start weekday.
                            </p>
                            <InputError
                                message={errors['lease.rent_due_weekday']}
                            />
                        </div>
                    ) : rentFrequency === 'yearly' ? (
                        <>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`${idPrefix}-lease-rent_due_month`}
                                >
                                    Rent due month
                                </Label>
                                <input
                                    type="hidden"
                                    name="lease[rent_due_month]"
                                    value={rentDueMonth}
                                />
                                <Select
                                    value={rentDueMonth}
                                    onValueChange={setRentDueMonth}
                                    disabled={termsLocked}
                                >
                                    <SelectTrigger
                                        id={`${idPrefix}-lease-rent_due_month`}
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select month" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {MONTHS.map((month, index) => (
                                            <SelectItem
                                                key={month}
                                                value={String(index + 1)}
                                            >
                                                {month}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={errors['lease.rent_due_month']}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`${idPrefix}-lease-rent_due_day`}
                                >
                                    Rent due day
                                </Label>
                                <Input
                                    id={`${idPrefix}-lease-rent_due_day`}
                                    name="lease[rent_due_day]"
                                    type="number"
                                    min="1"
                                    max="31"
                                    placeholder="5"
                                    value={rentDueDay}
                                    disabled={termsLocked}
                                    onChange={(event) => {
                                        setRentDueDay(event.target.value);
                                        setFieldError(
                                            'lease.rent_due_day',
                                            null,
                                        );
                                    }}
                                    onBlur={(event) =>
                                        setFieldError(
                                            'lease.rent_due_day',
                                            validateField(
                                                'lease.rent_due_day',
                                                event.target.value,
                                            ),
                                        )
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Day of the due month rent falls due.
                                </p>
                                <InputError
                                    message={
                                        errors['lease.rent_due_day'] ??
                                        fieldErrors['lease.rent_due_day']
                                    }
                                />
                            </div>
                        </>
                    ) : (
                        <div className="grid gap-2">
                            <Label htmlFor={`${idPrefix}-lease-rent_due_day`}>
                                Rent due day
                            </Label>
                            <Input
                                id={`${idPrefix}-lease-rent_due_day`}
                                name="lease[rent_due_day]"
                                type="number"
                                min="1"
                                max="31"
                                placeholder="5"
                                value={rentDueDay}
                                disabled={termsLocked}
                                onChange={(event) => {
                                    setRentDueDay(event.target.value);
                                    setFieldError('lease.rent_due_day', null);
                                }}
                                onBlur={(event) =>
                                    setFieldError(
                                        'lease.rent_due_day',
                                        validateField(
                                            'lease.rent_due_day',
                                            event.target.value,
                                        ),
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Day of the month rent falls due. Empty means the
                                lease start anniversary.
                            </p>
                            <InputError
                                message={
                                    errors['lease.rent_due_day'] ??
                                    fieldErrors['lease.rent_due_day']
                                }
                            />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}-lease-deposit`}>
                            Deposit
                        </Label>
                        <Input
                            id={`${idPrefix}-lease-deposit`}
                            name="lease[deposit]"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="25000"
                            defaultValue={lease.deposit ?? ''}
                            disabled={termsLocked}
                        />
                        <InputError message={errors['lease.deposit']} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}-lease-currency`}>
                            Currency
                        </Label>
                        <input
                            type="hidden"
                            name="lease[currency]"
                            value={currency}
                        />
                        <Select
                            value={currency}
                            onValueChange={setCurrency}
                            disabled={termsLocked}
                        >
                            <SelectTrigger
                                id={`${idPrefix}-lease-currency`}
                                className="w-full"
                            >
                                <SelectValue placeholder="Select currency" />
                            </SelectTrigger>
                            <SelectContent>
                                {CURRENCIES.map((value) => (
                                    <SelectItem key={value} value={value}>
                                        {value}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors['lease.currency']} />
                    </div>

                    {showMoveIn && (
                        <div className="grid gap-2 md:col-span-2">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-sm leading-none font-medium">
                                    Move-in extras{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (optional)
                                    </span>
                                </span>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={addExtra}
                                    disabled={termsLocked}
                                >
                                    <Plus className="size-4" />
                                    Add extra
                                </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Parking, pets and one-time fees on every lease
                                this move-in creates — the tenant sees them in
                                the agreement before signing.
                            </p>
                            {extras.map((extra, index) => (
                                <div
                                    key={index}
                                    className="grid gap-2 rounded-lg border border-border-strong p-3 sm:grid-cols-[1fr_8rem_8rem_auto]"
                                >
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`${idPrefix}-extra-name-${index}`}
                                        >
                                            Name
                                        </Label>
                                        <Input
                                            id={`${idPrefix}-extra-name-${index}`}
                                            name={`lease[extras][${index}][name]`}
                                            placeholder="Parking"
                                            defaultValue={extra.name}
                                            disabled={termsLocked}
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `lease.extras.${index}.name`
                                                ]
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`${idPrefix}-extra-kind-${index}`}
                                        >
                                            Billing
                                        </Label>
                                        <input
                                            type="hidden"
                                            name={`lease[extras][${index}][kind]`}
                                            value={extra.kind}
                                        />
                                        <Select
                                            value={extra.kind}
                                            onValueChange={(value) =>
                                                updateExtra(index, {
                                                    kind:
                                                        value === 'one_time'
                                                            ? 'one_time'
                                                            : 'fixed',
                                                })
                                            }
                                            disabled={termsLocked}
                                        >
                                            <SelectTrigger
                                                id={`${idPrefix}-extra-kind-${index}`}
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="fixed">
                                                    Every period
                                                </SelectItem>
                                                <SelectItem value="one_time">
                                                    One-time
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={
                                                errors[
                                                    `lease.extras.${index}.kind`
                                                ]
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`${idPrefix}-extra-amount-${index}`}
                                        >
                                            Amount
                                        </Label>
                                        <Input
                                            id={`${idPrefix}-extra-amount-${index}`}
                                            name={`lease[extras][${index}][amount]`}
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="2000"
                                            defaultValue={extra.amount}
                                            disabled={termsLocked}
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `lease.extras.${index}.amount`
                                                ]
                                            }
                                        />
                                    </div>
                                    <div className="flex items-end">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => removeExtra(index)}
                                            disabled={termsLocked}
                                            aria-label={`Remove extra ${index + 1}`}
                                        >
                                            <X className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {showMoveIn && (
                        <div className="grid gap-4 rounded-lg border border-border-strong bg-muted/40 p-4 md:col-span-2">
                            <div className="flex items-start gap-2">
                                <Checkbox
                                    id={`${idPrefix}-move-in-now`}
                                    checked={moveInNow}
                                    onCheckedChange={(checked) =>
                                        setMoveInNow(checked === true)
                                    }
                                />
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor={`${idPrefix}-move-in-now`}
                                        className="cursor-pointer"
                                    >
                                        Collecting the move-in payment now
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Records the deposit plus first-period
                                        rent and starts the lease today. Off
                                        means a booking — the occupant stays
                                        inactive until moved in from their
                                        record.
                                    </p>
                                </div>
                            </div>
                            <input
                                type="hidden"
                                name="move_in_now"
                                value={moveInNow ? '1' : ''}
                            />

                            {moveInNow && (
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`${idPrefix}-move-in-date`}
                                        >
                                            Move-in date
                                        </Label>
                                        <Input
                                            id={`${idPrefix}-move-in-date`}
                                            name="move_in_date"
                                            type="date"
                                            defaultValue={new Date()
                                                .toISOString()
                                                .slice(0, 10)}
                                        />
                                        <InputError
                                            message={errors.move_in_date}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`${idPrefix}-payment-method`}
                                        >
                                            Payment method
                                        </Label>
                                        <input
                                            type="hidden"
                                            name="payment_method"
                                            value={paymentMethod}
                                        />
                                        <Select
                                            value={paymentMethod}
                                            onValueChange={setPaymentMethod}
                                        >
                                            <SelectTrigger
                                                id={`${idPrefix}-payment-method`}
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Select method" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {[
                                                    'cash',
                                                    'mpesa',
                                                    'bank',
                                                    'cheque',
                                                    'card',
                                                    'other',
                                                ].map((value) => (
                                                    <SelectItem
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {value === 'mpesa'
                                                            ? 'M-Pesa'
                                                            : value
                                                                  .charAt(0)
                                                                  .toUpperCase() +
                                                              value.slice(1)}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.payment_method}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`${idPrefix}-payment-reference`}
                                        >
                                            Reference (optional)
                                        </Label>
                                        <Input
                                            id={`${idPrefix}-payment-reference`}
                                            name="payment_reference"
                                            placeholder="Receipt no."
                                        />
                                        <InputError
                                            message={errors.payment_reference}
                                        />
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor={`${idPrefix}-lease-agreement_text`}>
                            Lease agreement (optional)
                        </Label>

                        {templateList.length > 0 && (
                            <div className="grid gap-1">
                                <Label
                                    htmlFor={`${idPrefix}-template_picker`}
                                    className="text-xs text-muted-foreground"
                                >
                                    Start from a saved template
                                </Label>
                                <Select
                                    value={selectedTemplateId}
                                    onValueChange={applyTemplate}
                                >
                                    <SelectTrigger
                                        id={`${idPrefix}-template_picker`}
                                    >
                                        <SelectValue placeholder="Pick a template…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {templates.property.length > 0 && (
                                            <SelectGroup>
                                                <SelectLabel>
                                                    This property
                                                </SelectLabel>
                                                {templates.property.map(
                                                    (template) => (
                                                        <SelectItem
                                                            key={template.id}
                                                            value={String(
                                                                template.id,
                                                            )}
                                                        >
                                                            {template.name}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectGroup>
                                        )}
                                        {templates.organization.length > 0 && (
                                            <SelectGroup>
                                                <SelectLabel>
                                                    Organization-wide
                                                </SelectLabel>
                                                {templates.organization.map(
                                                    (template) => (
                                                        <SelectItem
                                                            key={template.id}
                                                            value={String(
                                                                template.id,
                                                            )}
                                                        >
                                                            {template.name}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectGroup>
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        <DocumentBuilder
                            name="lease[agreement_text]"
                            value={agreementText}
                            onChange={setAgreementText}
                            placeholder="Write the lease agreement. Formatting is preserved."
                            availableTokens={availableTokens}
                        />

                        {lease.agreement_document_url && (
                            <div className="flex items-center gap-3 text-sm">
                                <a
                                    href={lease.agreement_document_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary underline"
                                >
                                    {lease.agreement_document_name ??
                                        'Uploaded agreement'}
                                </a>
                                <label className="flex items-center gap-1 text-muted-foreground">
                                    <input
                                        type="checkbox"
                                        name="lease[remove_agreement_document]"
                                        value="1"
                                    />
                                    Remove
                                </label>
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label
                                htmlFor={`${idPrefix}-lease-agreement_document`}
                            >
                                {lease.agreement_document_url
                                    ? 'Replace with a new file (PDF/DOCX, max 10MB)'
                                    : 'Or upload a ready-made agreement (PDF/DOCX, max 10MB)'}
                            </Label>
                            <Input
                                id={`${idPrefix}-lease-agreement_document`}
                                name="lease[agreement_document]"
                                type="file"
                                accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            />
                            <InputError
                                message={errors['lease.agreement_document']}
                            />
                        </div>

                        <InputError message={errors['lease.agreement_text']} />
                    </div>
                </div>
            </div>

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Units in this property"
                    description={`Select the units this occupant rents in ${propertyName}.`}
                />
                {units.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No units yet. Add a unit to this property before
                        assigning occupants.
                    </p>
                ) : (
                    <div className="grid gap-2">
                        {unitIds.length > 0 && (
                            <div
                                className="flex flex-wrap gap-1.5"
                                aria-live="polite"
                            >
                                {unitIds.map((unitId) => {
                                    const picked = units.find(
                                        (unit) => unit.id === unitId,
                                    );

                                    if (!picked) {
                                        return null;
                                    }

                                    return (
                                        <span
                                            key={unitId}
                                            className="inline-flex items-center gap-1.5 rounded-full border border-transparent bg-brand-accent py-1 pr-1.5 pl-3 text-xs text-on-accent"
                                        >
                                            <span className="font-medium">
                                                {picked.name}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    toggleUnit(unitId)
                                                }
                                                aria-label={`Remove ${picked.name}`}
                                                className="inline-flex items-center justify-center rounded-full text-on-accent/70 transition-colors hover:text-on-accent"
                                            >
                                                <X className="size-3.5" />
                                            </button>
                                            <input
                                                type="hidden"
                                                name="unit_ids[]"
                                                value={String(unitId)}
                                            />
                                        </span>
                                    );
                                })}
                            </div>
                        )}
                        <div
                            className="relative"
                            onBlur={(event) => {
                                if (
                                    !event.currentTarget.contains(
                                        event.relatedTarget as Node | null,
                                    )
                                ) {
                                    setUnitPickerOpen(false);
                                }
                            }}
                        >
                            <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                id={`${idPrefix}-unit-search`}
                                type="text"
                                role="combobox"
                                aria-expanded={unitPickerOpen}
                                aria-controls={`${idPrefix}-unit-options`}
                                aria-label="Search units to assign"
                                autoComplete="off"
                                placeholder={
                                    unitIds.length === 0
                                        ? 'Search units to add...'
                                        : 'Search to add more units...'
                                }
                                value={unitQuery}
                                onChange={(event) => {
                                    setUnitQuery(event.target.value);
                                    setUnitPickerOpen(true);
                                }}
                                onFocus={() => setUnitPickerOpen(true)}
                                onKeyDown={(event) => {
                                    if (event.key === 'Escape') {
                                        setUnitPickerOpen(false);
                                    }
                                }}
                                className="border-border-strong bg-background pr-3 pl-9"
                            />
                            {unitPickerOpen && (
                                <ul
                                    id={`${idPrefix}-unit-options`}
                                    role="listbox"
                                    aria-label="Matching units"
                                    className="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-input bg-popover p-1 shadow-md"
                                >
                                    {matchingUnits.length === 0 ? (
                                        <li className="px-3 py-2 text-sm text-muted-foreground">
                                            {unitQuery.trim() === ''
                                                ? 'No more units to add.'
                                                : `No units match “${unitQuery.trim()}”.`}
                                        </li>
                                    ) : (
                                        matchingUnits.map((unit) => {
                                            const selected = unitIds.includes(
                                                unit.id,
                                            );

                                            return (
                                                <li key={unit.id}>
                                                    <button
                                                        type="button"
                                                        role="option"
                                                        aria-selected={selected}
                                                        onMouseDown={(event) =>
                                                            event.preventDefault()
                                                        }
                                                        onClick={() =>
                                                            toggleUnit(unit.id)
                                                        }
                                                        className="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors hover:bg-muted"
                                                    >
                                                        <span className="flex size-4 shrink-0 items-center justify-center">
                                                            {selected && (
                                                                <Check className="size-4 text-primary" />
                                                            )}
                                                        </span>
                                                        <span className="min-w-0 flex-1 truncate font-medium">
                                                            {unit.name}
                                                        </span>
                                                        {unit.type && (
                                                            <span className="truncate text-xs text-muted-foreground">
                                                                {unit.type}
                                                            </span>
                                                        )}
                                                        <span
                                                            className={`shrink-0 rounded-full px-2 py-0.5 text-xs ${
                                                                unit.status ===
                                                                'vacant'
                                                                    ? 'bg-muted text-muted-foreground'
                                                                    : 'bg-primary/10 text-primary'
                                                            }`}
                                                        >
                                                            {unit.status}
                                                        </span>
                                                    </button>
                                                </li>
                                            );
                                        })
                                    )}
                                </ul>
                            )}
                        </div>
                        <InputError message={errors.unit_ids} />
                    </div>
                )}
            </div>
        </>
    );
}
