import { Head, router } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { Printer, Sparkles } from 'lucide-react';
import { useRef, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { page as draftingPage } from '@/routes/tenant/drafting';
import { end as endLease, index as leasesIndex, renewalSuggestion } from '@/routes/tenant/leases';
import { agreement as agreementPrintRoute } from '@/routes/tenant/occupants';

type LeaseRow = {
    id: number;
    occupant_id: number;
    occupant_name: string;
    unit_name: string | null;
    rent_amount: string | null;
    currency: string | null;
    starts_at: string | null;
    ends_at: string | null;
    status: string;
    is_active: boolean;
    property_name?: string | null;
    expiring_soon?: boolean;
    has_agreement_text: boolean;
    has_agreement_document: boolean;
};

type Props = {
    leases: {
        data: LeaseRow[];
        current_page: number;
        last_page: number;
        links?: unknown;
    };
    filters: { status: string };
};

const statusStyles: Record<string, string> = {
    active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    ended: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-400',
};

export default function LeasesIndex({ leases, filters }: Props) {
    const { context } = usePage().props;
    const permissions = context?.permissions ?? [];
    const canEndLease = permissions.includes('lease.delete');
    const aiEnabled = usePage().props.auth?.ai_enabled ?? false;

    const [status, setStatus] = useState(filters.status);
    const [endTarget, setEndTarget] = useState<LeaseRow | null>(null);
    const [passwordError, setPasswordError] = useState('');
    const [suggestTarget, setSuggestTarget] = useState<LeaseRow | null>(null);
    const [suggestion, setSuggestion] = useState<{ suggested_rent: number; reasoning: string | null } | null>(null);
    const [suggestBusy, setSuggestBusy] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const fetchSuggestion = () => {
        if (!suggestTarget) {
            return;
        }

        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

        setSuggestBusy(true);

        fetch(
            renewalSuggestion({ property: context?.property?.slug ?? '', lease: suggestTarget.id }).url,
            { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } },
        )
            .then(async (response) => response.json())
            .then((data) => setSuggestion(data.error ? null : data))
            .finally(() => setSuggestBusy(false));
    };

    const submitEndLease = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!endTarget) {
            return;
        }

        const password = String(new FormData(event.currentTarget).get('password') ?? '');

        router.post(
            endLease({ property: context?.property?.slug ?? '', lease: endTarget.id }).url,
            { password },
            {
                preserveScroll: true,
                onSuccess: () => setEndTarget(null),
                onError: (errors) => {
                    setPasswordError(errors.password ?? '');
                    passwordInput.current?.focus();
                },
            },
        );
    };

    const applyStatusFilter = (value: string) => {
        const expiring = value === '__expiring';

        setStatus(expiring ? 'active' : value);

        router.get(
            leasesIndex.url(
                { property: context?.property?.slug ?? '' },
                { query: { status: expiring ? 'active' : value, expiring: expiring ? '1' : undefined } },
            ),
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Leases" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Leases"
                        description="Every lease in this property, with printable agreements."
                    />
                    <Select value={status || 'all'} onValueChange={applyStatusFilter}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="ended">Ended</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {leases.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No leases found. Assign an occupant to a unit to create one.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-input">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                    <th className="px-3 py-2">Unit</th>
                                    <th className="px-3 py-2">Occupant</th>
                                    <th className="px-3 py-2">Rent</th>
                                    <th className="px-3 py-2">Start</th>
                                    <th className="px-3 py-2">End</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {leases.data.map((lease) => (
                                    <tr key={lease.id} className="border-b last:border-0">
                                        <td className="px-3 py-2 font-medium">{lease.unit_name}</td>
                                        <td className="px-3 py-2">{lease.occupant_name}</td>
                                        <td className="px-3 py-2">
                                            {lease.rent_amount ? `${lease.rent_amount} ${lease.currency ?? ''}` : '—'}
                                        </td>
                                        <td className="px-3 py-2">{lease.starts_at ?? '—'}</td>
                                        <td className="px-3 py-2">{lease.ends_at ?? '—'}</td>
                                        <td className="px-3 py-2">
                                            <span className={`rounded px-1.5 py-0.5 text-xs capitalize ${statusStyles[lease.status] ?? ''}`}>
                                                {lease.status}
                                            </span>
                                            {lease.expiring_soon && (
                                                <Badge className="ml-1 bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                                    Expiring soon
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="flex justify-end gap-1">
                                                {lease.expiring_soon && (
                                                    <Button asChild variant="ghost" size="sm" data-test={`draft-renewal-${lease.id}`}>
                                                        <a
                                                            href={`${draftingPage().url}?${new URLSearchParams({
                                                                type: 'lease_expiry_notice',
                                                                tenant_name: lease.occupant_name ?? '',
                                                                expiry_date: lease.ends_at ?? '',
                                                            })}`}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >
                                                            Draft renewal
                                                        </a>
                                                    </Button>
                                                )}
                                                {(lease.has_agreement_text || lease.has_agreement_document) && (
                                                    <Button asChild variant="ghost" size="sm" data-test={`agreement-${lease.id}`}>
                                                        <a
                                                            href={agreementPrintRoute({
                                                                property: context?.property?.slug ?? '',
                                                                occupant: lease.occupant_id,
                                                            }).url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >
                                                            <Printer className="size-4" />
                                                            Agreement
                                                        </a>
                                                    </Button>
                                                )}
                                                {lease.expiring_soon && aiEnabled && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setSuggestTarget(lease);
                                                            setSuggestion(null);
                                                        }}
                                                    >
                                                        <Sparkles className="size-4" />
                                                        Suggest rent
                                                    </Button>
                                                )}
                                                {canEndLease && lease.is_active && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600"
                                                        onClick={() => setEndTarget(lease)}
                                                    >
                                                        End lease
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {leases.last_page > 1 && (
                    <div className="flex gap-1 text-sm">
                        {Array.from({ length: leases.last_page }, (_, i) => i + 1).map((page) => (
                            <Button
                                key={page}
                                variant={page === leases.current_page ? 'default' : 'outline'}
                                size="sm"
                                onClick={() =>
                                    router.get(
                                        leasesIndex.url(
                                            { property: context?.property?.slug ?? '' },
                                            { query: { status, page } },
                                        ),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {page}
                            </Button>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={endTarget !== null} onOpenChange={(open) => !open && setEndTarget(null)}>
                <DialogContent>
                    <DialogTitle>End lease for {endTarget?.occupant_name}?</DialogTitle>
                    <DialogDescription>
                        This ends the lease on unit {endTarget?.unit_name} today and keeps rental history.
                        Please enter your password to confirm.
                    </DialogDescription>
                    <form onSubmit={submitEndLease} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="end_lease_password" className="sr-only">
                                Password
                            </Label>
                            <PasswordInput
                                id="end_lease_password"
                                name="password"
                                ref={passwordInput}
                                placeholder="Password"
                                autoComplete="current-password"
                            />
                            <InputError message={passwordError} />
                        </div>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" variant="destructive">
                                End lease
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={suggestTarget !== null} onOpenChange={(open) => !open && setSuggestTarget(null)}>
                <DialogContent>
                    <DialogTitle>
                        Renewal suggestion — {suggestTarget?.occupant_name}
                    </DialogTitle>
                    <DialogDescription>
                        Unit {suggestTarget?.unit_name}, ends {suggestTarget?.ends_at}. Current rent:{' '}
                        {suggestTarget?.rent_amount} {suggestTarget?.currency}.
                    </DialogDescription>

                    {suggestBusy ? (
                        <p className="text-sm text-muted-foreground">Thinking…</p>
                    ) : suggestion ? (
                        <div className="space-y-3">
                            <p className="text-2xl font-semibold tabular-nums">
                                {suggestion.suggested_rent} {suggestTarget?.currency}
                                <span className="ml-1 text-sm font-normal text-muted-foreground">/ month</span>
                            </p>
                            {suggestion.reasoning && (
                                <p className="text-sm text-muted-foreground">{suggestion.reasoning}</p>
                            )}
                            <Button asChild size="sm" variant="outline">
                                <a
                                    href={`${draftingPage().url}?${new URLSearchParams({
                                        type: 'lease_expiry_notice',
                                        tenant_name: suggestTarget?.occupant_name ?? '',
                                        expiry_date: suggestTarget?.ends_at ?? '',
                                        suggested_rent: String(suggestion.suggested_rent),
                                    })}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Draft renewal letter with this rent
                                </a>
                            </Button>
                        </div>
                    ) : (
                        <Button onClick={fetchSuggestion}>
                            <Sparkles className="size-4" />
                            Generate suggestion
                        </Button>
                    )}

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="secondary">Close</Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
