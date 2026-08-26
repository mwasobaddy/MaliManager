import { Head } from '@inertiajs/react';
import { Copy, Wand2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { generate as generateDraft, page as draftingPage } from '@/routes/tenant/drafting';

type Props = {
    enabled: boolean;
};

const TYPES = [
    { value: 'rent_reminder', label: 'Rent reminder', fields: ['tenant_name', 'property_name', 'amount_due', 'due_date'] },
    { value: 'lease_expiry_notice', label: 'Lease expiry notice', fields: ['tenant_name', 'property_name', 'expiry_date', 'monthly_rent', 'suggested_rent'] },
    { value: 'move_out_letter', label: 'Move-out letter', fields: ['tenant_name', 'unit_name', 'move_out_date'] },
    { value: 'listing_description', label: 'Listing description', fields: ['property_name', 'bedrooms', 'key_features'] },
] as const;

function prefillFromQuery(): { type: string; fields: Record<string, string>; tone: string } {
    const params = new URLSearchParams(window.location.search);

    const typeParam = params.get('type') ?? '';
    const type = TYPES.some((t) => t.value === typeParam) ? typeParam : TYPES[0].value;

    const fieldDefs = TYPES.find((t) => t.value === type)?.fields ?? [];
    const fields: Record<string, string> = {};

    for (const field of fieldDefs) {
        const value = params.get(field);

        if (value) {
            fields[field] = value;
        }
    }

    return {
        type,
        fields,
        tone: params.get('tone') === 'formal' ? 'formal' : 'friendly',
    };
}

export default function Drafting({ enabled }: Props) {
    const initial = prefillFromQuery();
    const [type, setType] = useState<string>(initial.type);
    const [tone, setTone] = useState(initial.tone);
    const [fields, setFields] = useState<Record<string, string>>(initial.fields);
    const [draft, setDraft] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [copied, setCopied] = useState(false);

    const activeType = TYPES.find((t) => t.value === type) ?? TYPES[0];

    const generate = () => {
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

        setBusy(true);
        setError('');
        setDraft('');

        fetch(generateDraft().url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                type,
                tone,
                fields: Object.fromEntries(
                    activeType.fields.map((field) => [field, fields[field] ?? '']),
                ),
            }),
        })
            .then(async (response) => response.json())
            .then((data) => {
                if (data.draft) {
                    setDraft(data.draft);
                } else {
                    setError(data.error ?? 'Draft failed.');
                }
            })
            .catch(() => setError('Draft failed.'))
            .finally(() => setBusy(false));
    };

    return (
        <>
            <Head title="AI drafting" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="small"
                    title="AI drafting"
                    description="Generate tenant letters and listing descriptions. Copy the result — nothing is sent automatically."
                />

                {!enabled ? (
                    <p className="text-sm text-muted-foreground">
                        AI is not configured yet. Add an API key under Organization → AI settings
                        or Settings → AI.
                    </p>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>What should we draft?</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label>Type</Label>
                                    <Select value={type} onValueChange={(v) => {
                                        setType(v);
                                        setFields({});
                                    }}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            {TYPES.map((t) => (
                                                <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid gap-2">
                                    <Label>Tone</Label>
                                    <Select value={tone} onValueChange={setTone}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="friendly">Friendly</SelectItem>
                                            <SelectItem value="formal">Formal</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                {activeType.fields.map((field) => (
                                    <div key={field} className="grid gap-2">
                                        <Label htmlFor={`field-${field}`}>
                                            {field.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                                        </Label>
                                        <Input
                                            id={`field-${field}`}
                                            value={fields[field] ?? ''}
                                            onChange={(e) => setFields((prev) => ({ ...prev, [field]: e.target.value }))}
                                        />
                                    </div>
                                ))}

                                <Button onClick={generate} disabled={busy}>
                                    <Wand2 className="size-4" />
                                    {busy ? 'Writing…' : 'Generate draft'}
                                </Button>
                            </CardContent>
                        </Card>

                        <Card className="flex min-h-[300px] flex-col">
                            <CardHeader className="flex-row items-center justify-between">
                                <CardTitle>Draft</CardTitle>
                                {draft && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            navigator.clipboard.writeText(draft);
                                            setCopied(true);
                                            setTimeout(() => setCopied(false), 1500);
                                        }}
                                    >
                                        <Copy className="size-4" />
                                        {copied ? 'Copied!' : 'Copy'}
                                    </Button>
                                )}
                            </CardHeader>
                            <CardContent className="min-h-0 flex-1">
                                {draft ? (
                                    <pre className="whitespace-pre-wrap font-sans text-sm">{draft}</pre>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Your generated draft appears here.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </>
    );
}
