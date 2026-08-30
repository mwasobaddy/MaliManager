import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { destroy as destroyAiKey, update as updateAiSettings } from '@/routes/tenant/ai-settings';

type Member = { id: number; name: string; email: string };

type ModelGuidance = { tested: string[]; note: string | null };

type Props = {
    setting: {
        provider: string | null;
        model: string | null;
        base_url: string | null;
        has_key: boolean;
        masked_key: string | null;
        features: string[];
        allow_all_members: boolean;
        allowed_user_ids: number[];
    };
    providers: { value: string; label: string }[];
    models: Record<string, string[]>;
    modelGuidance: Record<string, ModelGuidance>;
    defaultBaseUrls: Record<string, string | null>;
    features: { value: string; label: string }[];
    members: Member[];
    usage: { name: string; calls: number; tokens: number }[];
};

export default function AiSettings({
    setting,
    providers,
    models,
    modelGuidance,
    defaultBaseUrls,
    features,
    members,
    usage,
}: Props) {
    const [provider, setProvider] = useState(setting.provider ?? 'openai');
    const [baseUrl, setBaseUrl] = useState(setting.base_url ?? defaultBaseUrls[setting.provider ?? 'openai'] ?? '');
    const [model, setModel] = useState(setting.model ?? '');
    const [apiKey, setApiKey] = useState('');
    const [enabledFeatures, setEnabledFeatures] = useState<string[]>(setting.features);
    const [allowAll, setAllowAll] = useState(setting.allow_all_members);
    const [allowedIds, setAllowedIds] = useState<number[]>(setting.allowed_user_ids);

    const toggleFeature = (value: string) => {
        setEnabledFeatures((prev) =>
            prev.includes(value) ? prev.filter((v) => v !== value) : [...prev, value],
        );
    };

    const toggleMember = (id: number) => {
        setAllowedIds((prev) =>
            prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id],
        );
    };

    const save = () => {
        router.put(updateAiSettings().url, {
            provider,
            model,
            api_key: apiKey || undefined,
            base_url: baseUrl || undefined,
            features: enabledFeatures,
            allow_all_members: allowAll,
            allowed_user_ids: allowedIds,
        }, { preserveScroll: true });
    };

    const removeKey = () => {
        router.delete(destroyAiKey().url, { preserveScroll: true });
    };

    return (
        <>
            <Head title="AI settings" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="small"
                    title="AI settings"
                    description="Bring your own LLM API key. Members you allow can use AI features with your key — usage is logged."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Credential</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Provider</Label>
                            <Select
                                value={provider}
                                onValueChange={(v) => {
                                    setProvider(v);
                                    setModel(models[v]?.[0] ?? '');
                                    setBaseUrl(defaultBaseUrls[v] ?? '');
                                }}
                            >
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {providers.map((p) => (
                                        <SelectItem key={p.value} value={p.value}>{p.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="model">Model</Label>
                            {(models[provider]?.length ?? 0) > 0 ? (
                                <Select value={model} onValueChange={setModel}>
                                    <SelectTrigger><SelectValue placeholder="Pick a model…" /></SelectTrigger>
                                    <SelectContent>
                                        {models[provider].map((m) => (
                                            <SelectItem key={m} value={m}>
                                                {m}
                                                {modelGuidance[provider]?.tested.includes(m) && (
                                                    <span className="ml-2 text-emerald-600 dark:text-emerald-400">
                                                        ✓ tested
                                                    </span>
                                                )}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <Input id="model" value={model} onChange={(e) => setModel(e.target.value)} />
                            )}
                            {modelGuidance[provider]?.tested.length > 0 && (
                                <p className="text-xs text-emerald-600 dark:text-emerald-400">
                                    ✓ Tested &amp; working with this assistant:{' '}
                                    {modelGuidance[provider].tested.join(', ')}
                                </p>
                            )}
                            {modelGuidance[provider]?.note && (
                                <p className="text-xs text-muted-foreground">{modelGuidance[provider].note}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="base_url" className="text-muted-foreground">
                                Base URL <span className="font-normal">(optional — for proxies/self-hosted endpoints)</span>
                            </Label>
                            <Input
                                id="base_url"
                                type="url"
                                placeholder="https://integrate.api.nvidia.com/v1"
                                value={baseUrl}
                                onChange={(e) => setBaseUrl(e.target.value)}
                            />
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="api_key">
                                {setting.has_key
                                    ? `API key (current: ${setting.masked_key})`
                                    : 'API key'}
                            </Label>
                            <Input
                                id="api_key"
                                type="password"
                                autoComplete="off"
                                placeholder={setting.has_key ? 'Leave empty to keep current key' : 'sk-…'}
                                value={apiKey}
                                onChange={(e) => setApiKey(e.target.value)}
                            />
                            <InputError message={(usePage().props.errors as Record<string, string> | undefined)?.api_key ?? ''} />
                            <div className="flex items-center gap-3">
                                {setting.has_key && (
                                    <>
                                        <Badge variant="secondary">Key active</Badge>
                                        <Button variant="ghost" size="sm" className="text-red-600" onClick={removeKey}>
                                            Remove key
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>

                        <div className="md:col-span-2">
                            <Label className="mb-2 block">Enabled features</Label>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {features.map((feature) => (
                                    <label key={feature.value} className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={enabledFeatures.includes(feature.value)}
                                            onCheckedChange={() => toggleFeature(feature.value)}
                                        />
                                        {feature.label}
                                    </label>
                                ))}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Who can use it</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={allowAll}
                                onCheckedChange={(checked) => setAllowAll(checked === true)}
                            />
                            All organization members
                        </label>

                        {!allowAll && (
                            <div className="max-h-64 space-y-1 overflow-y-auto rounded-md border border-input p-2">
                                {members.map((member) => (
                                    <label
                                        key={member.id}
                                        className="flex items-center justify-between gap-2 rounded px-2 py-1 text-sm hover:bg-accent"
                                    >
                                        <span className="flex items-center gap-2">
                                            <Checkbox
                                                checked={allowedIds.includes(member.id)}
                                                onCheckedChange={() => toggleMember(member.id)}
                                            />
                                            {member.name}
                                            <span className="text-muted-foreground">{member.email}</span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                        )}

                        <p className="text-xs text-muted-foreground">
                            Users can also add a personal API key in Settings → AI — personal keys take precedence.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Usage this month</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {usage.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No AI usage recorded yet.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <th className="px-3 py-2">User</th>
                                        <th className="px-3 py-2">Calls</th>
                                        <th className="px-3 py-2 text-right">Tokens</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {usage.map((row) => (
                                        <tr key={row.name} className="border-b last:border-0">
                                            <td className="px-3 py-2 font-medium">{row.name}</td>
                                            <td className="px-3 py-2 tabular-nums">{row.calls}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {row.tokens.toLocaleString()}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                        <InputError message="" />
                    </CardContent>
                </Card>

                <div>
                    <Button onClick={save}>Save AI settings</Button>
                </div>
            </div>
        </>
    );
}
