import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import {
    destroy as destroyPersonalAi,
    update as updatePersonalAi,
} from '@/routes/settings/personal-ai';

type ModelGuidance = { tested: string[]; note: string | null };

type Props = {
    setting: {
        provider: string | null;
        model: string | null;
        base_url?: string | null;
        has_key: boolean;
        masked_key: string | null;
        features: string[];
    };
    providers: { value: string; label: string }[];
    models: Record<string, string[]>;
    modelGuidance: Record<string, ModelGuidance>;
    defaultBaseUrls: Record<string, string | null>;
    features: { value: string; label: string }[];
    usage?: { calls: number; tokens: number };
};

export default function PersonalAi({
    setting,
    providers,
    models,
    modelGuidance,
    defaultBaseUrls,
    features,
    usage = { calls: 0, tokens: 0 },
}: Props) {
    const pageErrors = (usePage().props.errors ?? {}) as Record<string, string>;

    const [provider, setProvider] = useState(setting.provider ?? 'openai');
    const [model, setModel] = useState(setting.model ?? '');
    const [apiKey, setApiKey] = useState('');
    const [baseUrl, setBaseUrl] = useState(
        setting.base_url ?? defaultBaseUrls[setting.provider ?? 'openai'] ?? '',
    );
    const [enabledFeatures, setEnabledFeatures] = useState<string[]>(
        setting.features,
    );

    const toggleFeature = (value: string) => {
        setEnabledFeatures((prev) =>
            prev.includes(value)
                ? prev.filter((v) => v !== value)
                : [...prev, value],
        );
    };

    return (
        <>
            <Head title="AI settings" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Personal AI settings"
                    description="Your own LLM API key. It takes precedence over your organization's key and is used only for you."
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
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {providers.map((p) => (
                                        <SelectItem
                                            key={p.value}
                                            value={p.value}
                                        >
                                            {p.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="model">Model</Label>
                            {(models[provider]?.length ?? 0) > 0 ? (
                                <Select value={model} onValueChange={setModel}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pick a model…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {models[provider].map((m) => (
                                            <SelectItem key={m} value={m}>
                                                {m}
                                                {modelGuidance[
                                                    provider
                                                ]?.tested.includes(m) && (
                                                    <span className="ml-2 text-emerald-600 dark:text-emerald-400">
                                                        ✓ tested
                                                    </span>
                                                )}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <Input
                                    id="model"
                                    value={model}
                                    onChange={(e) => setModel(e.target.value)}
                                />
                            )}
                            {modelGuidance[provider]?.tested.length > 0 && (
                                <p className="text-xs text-emerald-600 dark:text-emerald-400">
                                    ✓ Tested &amp; working with this assistant:{' '}
                                    {modelGuidance[provider].tested.join(', ')}
                                </p>
                            )}
                            {modelGuidance[provider]?.note && (
                                <p className="text-xs text-muted-foreground">
                                    {modelGuidance[provider].note}
                                </p>
                            )}
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
                                placeholder={
                                    setting.has_key
                                        ? 'Leave empty to keep current key'
                                        : 'sk-…'
                                }
                                value={apiKey}
                                onChange={(e) => setApiKey(e.target.value)}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="base_url">
                                    Base URL (optional)
                                </Label>
                                <Input
                                    id="base_url"
                                    type="url"
                                    placeholder="https://integrate.api.nvidia.com/v1"
                                    value={baseUrl}
                                    onChange={(e) => setBaseUrl(e.target.value)}
                                />
                            </div>
                            <InputError message={pageErrors.api_key ?? ''} />
                            <p className="text-xs text-muted-foreground">
                                Your usage this month: {usage.calls} call
                                {usage.calls === 1 ? '' : 's'} ·{' '}
                                {usage.tokens.toLocaleString()} tokens
                            </p>
                            {setting.has_key && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="w-fit text-red-600"
                                    onClick={() =>
                                        router.delete(destroyPersonalAi().url, {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    Remove personal key
                                </Button>
                            )}
                        </div>

                        <div className="md:col-span-2">
                            <Label className="mb-2 block">Use my key for</Label>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {features.map((feature) => (
                                    <label
                                        key={feature.value}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={enabledFeatures.includes(
                                                feature.value,
                                            )}
                                            onCheckedChange={() =>
                                                toggleFeature(feature.value)
                                            }
                                        />
                                        {feature.label}
                                    </label>
                                ))}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div>
                    <Button
                        onClick={() =>
                            router.put(
                                updatePersonalAi().url,
                                {
                                    provider,
                                    model,
                                    base_url: baseUrl || undefined,
                                    api_key: apiKey || undefined,
                                    features: enabledFeatures,
                                },
                                { preserveScroll: true },
                            )
                        }
                        disabled={!setting.has_key && !apiKey}
                    >
                        Save AI settings
                    </Button>
                </div>
            </div>
        </>
    );
}
