import { DoorOpen } from 'lucide-react';
import { useEffect, useState } from 'react';
import DocumentBuilder from '@/components/document-builder';
import { FrequencySelect } from '@/components/frequency-select';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import type { TemplateToken } from '@/components/rich-text-editor';
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
    email?: string;
    phone?: string | null;
    national_id?: string | null;
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
}: Props) {
    const templateList = [...templates.property, ...templates.organization];
    const [selectedTemplateId, setSelectedTemplateId] = useState('');
    const [status, setStatus] = useState(defaults.status ?? 'active');
    const [unitIds, setUnitIds] = useState<number[]>(defaults.unit_ids ?? []);
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

    // Prefill billing from the allocated unit when a fresh lease is being
    // drafted (no lease defaults yet): one unit, no ambiguity. Anything the
    // landlord then edits stays edited — this only fires on selection
    // change. Editing an occupant never reaches here, because existing
    // leases keep their own terms.
    const hasLeaseDefaults = defaults.lease != null;
    useEffect(() => {
        if (hasLeaseDefaults || unitIds.length !== 1) {
            return;
        }

        const picked = units.find((unit) => unit.id === unitIds[0]);

        if (!picked) {
            return;
        }

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
                        defaultValue={defaults.first_name ?? ''}
                    />
                    <InputError message={errors.first_name} />
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
                    />
                    <InputError message={errors.email} />
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
            </div>

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Lease details"
                    description={leaseSectionHint}
                />
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
                            . Editable.
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
                                    onChange={(event) =>
                                        setRentDueDay(event.target.value)
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Day of the due month rent falls due.
                                </p>
                                <InputError
                                    message={errors['lease.rent_due_day']}
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
                                onChange={(event) =>
                                    setRentDueDay(event.target.value)
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Day of the month rent falls due. Empty means the
                                lease start anniversary.
                            </p>
                            <InputError
                                message={errors['lease.rent_due_day']}
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
                        <Select value={currency} onValueChange={setCurrency}>
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
                    <div className="grid gap-3">
                        {units.map((unit) => (
                            <label
                                key={unit.id}
                                className="flex items-center gap-3 rounded-lg border border-input p-3 text-sm transition-colors hover:bg-muted"
                            >
                                <Checkbox
                                    checked={unitIds.includes(unit.id)}
                                    onCheckedChange={() => {
                                        const next = unitIds.includes(unit.id)
                                            ? unitIds.filter(
                                                  (uid) => uid !== unit.id,
                                              )
                                            : [...unitIds, unit.id];
                                        setUnitIds(next);
                                    }}
                                />
                                <input
                                    type="hidden"
                                    name="unit_ids[]"
                                    value={String(unit.id)}
                                    disabled={!unitIds.includes(unit.id)}
                                />
                                <DoorOpen className="size-4 text-muted-foreground" />
                                <span className="font-medium">{unit.name}</span>
                                {unit.type && (
                                    <span className="text-muted-foreground">
                                        {unit.type}
                                    </span>
                                )}
                                <span className="ml-auto">
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-xs ${
                                            unit.status === 'vacant'
                                                ? 'bg-muted text-muted-foreground'
                                                : 'bg-primary/10 text-primary'
                                        }`}
                                    >
                                        {unit.status}
                                    </span>
                                </span>
                            </label>
                        ))}
                        <InputError message={errors.unit_ids} />
                    </div>
                )}
            </div>
        </>
    );
}
