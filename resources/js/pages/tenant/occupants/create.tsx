import { Form, Head, Link } from '@inertiajs/react';
import { DoorOpen } from 'lucide-react';
import { useState } from 'react';
import DocumentBuilder from '@/components/document-builder';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import type { TemplateToken } from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectGroup,
    SelectLabel,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as occupantsIndex, store } from '@/routes/tenant/occupants';

type Property = {
    id: number;
    name: string;
    slug: string;
};

type Unit = {
    id: number;
    name: string;
    type: string | null;
    status: string;
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

type Props = {
    property: Property;
    units: Unit[];
    templates: GroupedTemplates;
    availableTokens?: TemplateToken[];
};

export default function OccupantCreate({
    property,
    units,
    templates,
    availableTokens,
}: Props) {
    const [templateGroups] = useState<GroupedTemplates>(templates);
    const templateList = [
        ...templateGroups.property,
        ...templateGroups.organization,
    ];
    const [selectedTemplateId, setSelectedTemplateId] = useState('');
    const [status, setStatus] = useState('active');
    const [unitIds, setUnitIds] = useState<number[]>([]);
    const [rentFrequency, setRentFrequency] = useState('monthly');
    const [currency, setCurrency] = useState('KES');
    const [agreementText, setAgreementText] = useState('<p></p>');

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

    const toggleUnit = (id: number) => {
        setUnitIds((prev) =>
            prev.includes(id)
                ? prev.filter((uid) => uid !== id)
                : [...prev, id],
        );
    };

    return (
        <>
            <Head title="Add occupant" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Add an occupant"
                        description={`Assign someone to a unit in ${property.name}.`}
                    />
                    <Button asChild variant="outline">
                        <Link href={occupantsIndex(property.slug)}>
                            Back to occupants
                        </Link>
                    </Button>
                </div>

                <Form {...store.form(property.slug)} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-6 rounded-xl border border-input p-6 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="first_name">
                                        First name
                                    </Label>
                                    <Input
                                        id="first_name"
                                        name="first_name"
                                        type="text"
                                        required
                                        autoComplete="off"
                                        placeholder="Jane"
                                    />
                                    <InputError message={errors.first_name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="last_name">
                                        Last name (optional)
                                    </Label>
                                    <Input
                                        id="last_name"
                                        name="last_name"
                                        type="text"
                                        autoComplete="off"
                                        placeholder="Wanjiru"
                                    />
                                    <InputError message={errors.last_name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                        autoComplete="off"
                                        placeholder="jane@example.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">
                                        Phone (optional)
                                    </Label>
                                    <Input
                                        id="phone"
                                        name="phone"
                                        type="tel"
                                        autoComplete="off"
                                        placeholder="+254 700 000 000"
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="national_id">
                                        National ID (optional)
                                    </Label>
                                    <Input
                                        id="national_id"
                                        name="national_id"
                                        type="text"
                                        autoComplete="off"
                                        placeholder="12345678"
                                    />
                                    <InputError message={errors.national_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Status</Label>
                                    <input
                                        type="hidden"
                                        name="status"
                                        value={status}
                                    />
                                    <Select
                                        value={status}
                                        onValueChange={setStatus}
                                    >
                                        <SelectTrigger
                                            id="status"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select a status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">
                                                Active
                                            </SelectItem>
                                            <SelectItem value="inactive">
                                                Inactive
                                            </SelectItem>
                                            <SelectItem value="moved_out">
                                                Moved out
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.status} />
                                </div>
                            </div>

                            <div className="space-y-4 rounded-xl border border-input p-6">
                                <Heading
                                    variant="small"
                                    title="Lease details"
                                    description="Capture the rental terms. These power the renter's rental history."
                                />
                                <div className="grid gap-6 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="lease.starts_at">
                                            Lease start date
                                        </Label>
                                        <Input
                                            id="lease.starts_at"
                                            name="lease[starts_at]"
                                            type="date"
                                        />
                                        <InputError
                                            message={errors['lease.starts_at']}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="lease.rent_frequency">
                                            Rent frequency
                                        </Label>
                                        <input
                                            type="hidden"
                                            name="lease[rent_frequency]"
                                            value={rentFrequency}
                                        />
                                        <Select
                                            value={rentFrequency}
                                            onValueChange={setRentFrequency}
                                        >
                                            <SelectTrigger
                                                id="lease.rent_frequency"
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Select frequency" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="daily">
                                                    Daily
                                                </SelectItem>
                                                <SelectItem value="weekly">
                                                    Weekly
                                                </SelectItem>
                                                <SelectItem value="monthly">
                                                    Monthly
                                                </SelectItem>
                                                <SelectItem value="yearly">
                                                    Yearly
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={
                                                errors['lease.rent_frequency']
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="lease.rent_amount">
                                            Rent amount
                                        </Label>
                                        <Input
                                            id="lease.rent_amount"
                                            name="lease[rent_amount]"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="25000"
                                        />
                                        <InputError
                                            message={
                                                errors['lease.rent_amount']
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="lease.deposit">
                                            Deposit
                                        </Label>
                                        <Input
                                            id="lease.deposit"
                                            name="lease[deposit]"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="25000"
                                        />
                                        <InputError
                                            message={errors['lease.deposit']}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="lease.currency">
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
                                        >
                                            <SelectTrigger
                                                id="lease.currency"
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Select currency" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="KES">
                                                    KES
                                                </SelectItem>
                                                <SelectItem value="USD">
                                                    USD
                                                </SelectItem>
                                                <SelectItem value="EUR">
                                                    EUR
                                                </SelectItem>
                                                <SelectItem value="GBP">
                                                    GBP
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors['lease.currency']}
                                        />
                                    </div>

                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="lease.agreement_text">
                                            Lease agreement (optional)
                                        </Label>

                                        {templateList.length > 0 && (
                                            <div className="grid gap-1">
                                                <Label
                                                    htmlFor="template_picker"
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    Start from a saved template
                                                </Label>
                                                <Select
                                                    value={selectedTemplateId}
                                                    onValueChange={
                                                        applyTemplate
                                                    }
                                                >
                                                    <SelectTrigger id="template_picker">
                                                        <SelectValue placeholder="Pick a template…" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {templateGroups.property
                                                            .length > 0 && (
                                                            <SelectGroup>
                                                                <SelectLabel>
                                                                    This
                                                                    property
                                                                </SelectLabel>
                                                                {templateGroups.property.map(
                                                                    (
                                                                        template,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                template.id
                                                                            }
                                                                            value={String(
                                                                                template.id,
                                                                            )}
                                                                        >
                                                                            {
                                                                                template.name
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectGroup>
                                                        )}
                                                        {templateGroups
                                                            .organization
                                                            .length > 0 && (
                                                            <SelectGroup>
                                                                <SelectLabel>
                                                                    Organization-wide
                                                                </SelectLabel>
                                                                {templateGroups.organization.map(
                                                                    (
                                                                        template,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                template.id
                                                                            }
                                                                            value={String(
                                                                                template.id,
                                                                            )}
                                                                        >
                                                                            {
                                                                                template.name
                                                                            }
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

                                        <div className="grid gap-2">
                                            <Label htmlFor="lease.agreement_document">
                                                Or upload a ready-made agreement
                                                (PDF/DOCX, max 10MB)
                                            </Label>
                                            <Input
                                                id="lease.agreement_document"
                                                name="lease[agreement_document]"
                                                type="file"
                                                accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        'lease.agreement_document'
                                                    ]
                                                }
                                            />
                                        </div>

                                        <InputError
                                            message={
                                                errors['lease.agreement_text']
                                            }
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4 rounded-xl border border-input p-6">
                                <Heading
                                    variant="small"
                                    title="Units in this property"
                                    description="Select the units this occupant rents in {property.name}."
                                />
                                {units.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No units yet. Add a unit to this
                                        property before assigning occupants.
                                    </p>
                                ) : (
                                    <div className="grid gap-3">
                                        {units.map((unit) => (
                                            <label
                                                key={unit.id}
                                                className="flex items-center gap-3 rounded-lg border border-input p-3 text-sm transition-colors hover:bg-muted"
                                            >
                                                <Checkbox
                                                    checked={unitIds.includes(
                                                        unit.id,
                                                    )}
                                                    onCheckedChange={() =>
                                                        toggleUnit(unit.id)
                                                    }
                                                />
                                                <input
                                                    type="hidden"
                                                    name="unit_ids[]"
                                                    value={String(unit.id)}
                                                    disabled={
                                                        !unitIds.includes(
                                                            unit.id,
                                                        )
                                                    }
                                                />
                                                <DoorOpen className="size-4 text-muted-foreground" />
                                                <span className="font-medium">
                                                    {unit.name}
                                                </span>
                                                {unit.type && (
                                                    <span className="text-muted-foreground">
                                                        {unit.type}
                                                    </span>
                                                )}
                                                <span className="ml-auto">
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-xs ${
                                                            unit.status ===
                                                            'vacant'
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

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Adding…' : 'Add occupant'}
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href={occupantsIndex(property.slug)}>
                                        Cancel
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

OccupantCreate.layout = {
    breadcrumbs: [
        {
            title: 'Occupants',
            href: '',
        },
        {
            title: 'Add occupant',
        },
    ],
};
