import { Head, router } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import {
    FileText,
    Power,
    Printer,
    ScrollText,
    Send,
    Sparkles,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { OverlayFooter } from '@/components/overlay-footer';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Empty } from '@/components/ui/empty';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useOverlayParam } from '@/hooks/use-overlay-param';
import { formatMoney } from '@/lib/format-money';
import { page as draftingPage } from '@/routes/tenant/drafting';
import {
    end as endLease,
    index as leasesIndex,
    remind as remindLease,
    remindBulk as remindBulkLeases,
    renewalSuggestion,
} from '@/routes/tenant/leases';
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
    outstanding: string;
    is_overdue: boolean;
    days_overdue: number;
    last_reminded_ago: string | null;
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
    filters: { status: string; overdue: boolean };
};

export default function LeasesIndex({ leases, filters }: Props) {
    const { context } = usePage().props;
    const permissions = context?.permissions ?? [];
    const canEndLease = permissions.includes('lease.delete');
    const canRemind = permissions.includes('lease.manage');
    const aiEnabled = usePage().props.auth?.ai_enabled ?? false;

    const [status, setStatus] = useState(filters.status);
    const [overdueOnly, setOverdueOnly] = useState(filters.overdue);
    const [selected, setSelected] = useState<number[]>([]);
    const [passwordError, setPasswordError] = useState('');
    const { query, setOverlayParam } = useOverlayParam();

    // Both dialogs are addressed by the URL so they survive reloads and Back
    // closes them — never useState. The row may sit on another page, so fall
    // back to null rather than a stale row.
    const dialogLeaseId = Number(query.get('lease'));
    const dialogLease =
        Number.isFinite(dialogLeaseId) && dialogLeaseId > 0
            ? (leases.data.find((lease) => lease.id === dialogLeaseId) ?? null)
            : null;
    const endTarget = query.get('dialog') === 'end-lease' ? dialogLease : null;
    const suggestTarget =
        query.get('dialog') === 'suggest' ? dialogLease : null;
    const [suggestion, setSuggestion] = useState<{
        suggested_rent: number;
        reasoning: string | null;
    } | null>(null);
    const [suggestBusy, setSuggestBusy] = useState(false);

    function openLeaseDialog(lease: LeaseRow, dialog: 'end-lease' | 'suggest') {
        setPasswordError('');
        setOverlayParam('dialog', dialog, {
            keep: { lease: String(lease.id) },
        });
    }

    function closeLeaseDialog() {
        setOverlayParam('dialog', null, {
            replace: true,
            keep: { lease: '' },
        });
    }
    const passwordInput = useRef<HTMLInputElement>(null);

    const fetchSuggestion = () => {
        if (!suggestTarget) {
            return;
        }

        const csrf =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content ?? '';

        setSuggestBusy(true);

        fetch(
            renewalSuggestion({
                property: context?.property?.slug ?? '',
                lease: suggestTarget.id,
            }).url,
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

        const password = String(
            new FormData(event.currentTarget).get('password') ?? '',
        );

        router.post(
            endLease({
                property: context?.property?.slug ?? '',
                lease: endTarget.id,
            }).url,
            { password },
            {
                preserveScroll: true,
                onSuccess: () => closeLeaseDialog(),
                onError: (errors) => {
                    setPasswordError(errors.password ?? '');
                    passwordInput.current?.focus();
                },
            },
        );
    };

    const sendReminder = (lease: LeaseRow) => {
        router.post(
            remindLease({
                property: context?.property?.slug ?? '',
                lease: lease.id,
            }).url,
            {},
            { preserveScroll: true },
        );
    };

    const toggleSelected = (id: number) => {
        setSelected((prev) =>
            prev.includes(id)
                ? prev.filter((selectedId) => selectedId !== id)
                : [...prev, id],
        );
    };

    const sendBulkReminders = () => {
        router.post(
            remindBulkLeases({
                property: context?.property?.slug ?? '',
            }).url,
            { lease_ids: selected },
            {
                preserveScroll: true,
                onSuccess: () => setSelected([]),
            },
        );
    };

    const applyStatusFilter = (value: string) => {
        const expiring = value === '__expiring';
        const overdue = value === '__overdue';

        setStatus(expiring || overdue ? 'active' : value);
        setOverdueOnly(overdue);

        router.get(
            leasesIndex.url(
                { property: context?.property?.slug ?? '' },
                {
                    query: {
                        status: expiring || overdue ? 'active' : value,
                        expiring: expiring ? '1' : undefined,
                        overdue: overdue ? '1' : undefined,
                    },
                },
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
                        level="h1"
                        title="Leases"
                        description="Every lease in this property, with printable agreements."
                    />
                    <Select
                        value={overdueOnly ? '__overdue' : status || 'all'}
                        onValueChange={applyStatusFilter}
                    >
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="ended">Ended</SelectItem>
                            <SelectItem value="__overdue">Overdue</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {canRemind && selected.length > 0 && (
                    <div className="flex flex-wrap items-center gap-3 rounded-xl border border-input bg-card px-4 py-3">
                        <p className="text-sm text-muted-foreground">
                            {selected.length}{' '}
                            {selected.length === 1 ? 'lease' : 'leases'}{' '}
                            selected
                        </p>
                        <Button size="sm" onClick={sendBulkReminders}>
                            Send reminders
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setSelected([])}
                        >
                            Clear
                        </Button>
                    </div>
                )}

                {leases.data.length === 0 ? (
                    <Empty
                        icon={<ScrollText className="size-4" />}
                        title="No leases found"
                        description="Assign an occupant to a unit to create one."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-input">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    {canRemind && (
                                        <th className="w-8 px-3 py-2">
                                            <span className="sr-only">
                                                Select
                                            </span>
                                        </th>
                                    )}
                                    <th className="px-3 py-2">Unit</th>
                                    <th className="px-3 py-2">Occupant</th>
                                    <th className="px-3 py-2">Rent</th>
                                    <th className="px-3 py-2">Balance</th>
                                    <th className="px-3 py-2">Start</th>
                                    <th className="px-3 py-2">End</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {leases.data.map((lease) => (
                                    <tr
                                        key={lease.id}
                                        className="border-b last:border-0"
                                    >
                                        {canRemind && (
                                            <td className="px-3 py-2">
                                                <Checkbox
                                                    checked={selected.includes(
                                                        lease.id,
                                                    )}
                                                    disabled={!lease.is_overdue}
                                                    onCheckedChange={() =>
                                                        toggleSelected(lease.id)
                                                    }
                                                    aria-label={`Select ${lease.occupant_name || 'lease'} for reminders`}
                                                />
                                            </td>
                                        )}
                                        <td className="px-3 py-2 font-medium">
                                            {lease.unit_name}
                                        </td>
                                        <td className="px-3 py-2">
                                            {lease.occupant_name}
                                        </td>
                                        <td className="px-3 py-2">
                                            {formatMoney(
                                                lease.rent_amount,
                                                lease.currency,
                                            )}
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {lease.outstanding !== '0.00'
                                                ? formatMoney(
                                                      lease.outstanding,
                                                      lease.currency,
                                                  )
                                                : '—'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {lease.starts_at ?? '—'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {lease.ends_at ?? '—'}
                                        </td>
                                        <td className="px-3 py-2">
                                            <Badge
                                                variant={
                                                    lease.status === 'active'
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                                className="capitalize"
                                            >
                                                {lease.status}
                                            </Badge>
                                            {lease.expiring_soon && (
                                                <Badge className="ml-1 bg-status-warning-bg text-status-warning-fg">
                                                    Expiring soon
                                                </Badge>
                                            )}
                                            {lease.is_overdue && (
                                                <Badge
                                                    variant="danger"
                                                    className="ml-1"
                                                >
                                                    Overdue
                                                    {lease.days_overdue > 0
                                                        ? ` ${lease.days_overdue}d`
                                                        : ''}
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="flex justify-end gap-1">
                                                {canRemind &&
                                                    lease.is_overdue && (
                                                        <div className="flex flex-col items-end gap-0.5">
                                                            <ActionIconButton
                                                                icon={<Send />}
                                                                label={`Remind ${lease.occupant_name ?? 'occupant'} about overdue rent`}
                                                                color="view"
                                                                onClick={() =>
                                                                    sendReminder(
                                                                        lease,
                                                                    )
                                                                }
                                                            />
                                                            {lease.last_reminded_ago && (
                                                                <span className="pr-2 text-xs text-muted-foreground">
                                                                    Reminded{' '}
                                                                    {
                                                                        lease.last_reminded_ago
                                                                    }
                                                                </span>
                                                            )}
                                                        </div>
                                                    )}
                                                {lease.expiring_soon && (
                                                    <ActionIconButton
                                                        asChild
                                                        label="Draft renewal letter"
                                                        color="edit"
                                                    >
                                                        <a
                                                            href={`${draftingPage().url}?${new URLSearchParams(
                                                                {
                                                                    type: 'lease_expiry_notice',
                                                                    tenant_name:
                                                                        lease.occupant_name ??
                                                                        '',
                                                                    expiry_date:
                                                                        lease.ends_at ??
                                                                        '',
                                                                },
                                                            )}`}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            data-test={`draft-renewal-${lease.id}`}
                                                        >
                                                            <FileText />
                                                        </a>
                                                    </ActionIconButton>
                                                )}
                                                {(lease.has_agreement_text ||
                                                    lease.has_agreement_document) && (
                                                    <ActionIconButton
                                                        asChild
                                                        label="View agreement"
                                                        color="view"
                                                    >
                                                        <a
                                                            href={
                                                                agreementPrintRoute(
                                                                    {
                                                                        property:
                                                                            context
                                                                                ?.property
                                                                                ?.slug ??
                                                                            '',
                                                                        occupant:
                                                                            lease.occupant_id,
                                                                    },
                                                                ).url
                                                            }
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            data-test={`agreement-${lease.id}`}
                                                        >
                                                            <Printer />
                                                        </a>
                                                    </ActionIconButton>
                                                )}
                                                {lease.expiring_soon &&
                                                    aiEnabled && (
                                                        <ActionIconButton
                                                            icon={<Sparkles />}
                                                            label="Suggest renewal rent"
                                                            color="brand"
                                                            onClick={() => {
                                                                openLeaseDialog(
                                                                    lease,
                                                                    'suggest',
                                                                );
                                                                setSuggestion(
                                                                    null,
                                                                );
                                                            }}
                                                        />
                                                    )}
                                                {canEndLease &&
                                                    lease.is_active && (
                                                        <ActionIconButton
                                                            icon={<Power />}
                                                            label={`End lease for ${lease.occupant_name ?? 'occupant'}`}
                                                            color="danger"
                                                            onClick={() =>
                                                                openLeaseDialog(
                                                                    lease,
                                                                    'end-lease',
                                                                )
                                                            }
                                                        />
                                                    )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination
                    currentPage={leases.current_page}
                    lastPage={leases.last_page}
                    label="leases"
                    onChange={(page) =>
                        router.get(
                            leasesIndex.url(
                                { property: context?.property?.slug ?? '' },
                                { query: { status, page } },
                            ),
                            {},
                            { preserveScroll: true },
                        )
                    }
                />
            </div>

            <Dialog
                open={endTarget !== null}
                onOpenChange={(open) => !open && closeLeaseDialog()}
            >
                <DialogContent>
                    <DialogTitle>
                        End lease for {endTarget?.occupant_name}?
                    </DialogTitle>
                    <DialogDescription>
                        This ends the lease on unit {endTarget?.unit_name} today
                        and keeps rental history. Please enter your password to
                        confirm.
                    </DialogDescription>
                    <form onSubmit={submitEndLease} className="space-y-6">
                        <div className="grid gap-2">
                            <Label
                                htmlFor="end_lease_password"
                                className="sr-only"
                            >
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
                        <DialogFooter>
                            <OverlayFooter
                                cancelClosesDialog
                                actions={[
                                    {
                                        label: 'End lease',
                                        tone: 'danger',
                                        type: 'submit',
                                    },
                                ]}
                            />
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={suggestTarget !== null}
                onOpenChange={(open) => !open && closeLeaseDialog()}
            >
                <DialogContent>
                    <DialogTitle>
                        Renewal suggestion — {suggestTarget?.occupant_name}
                    </DialogTitle>
                    <DialogDescription>
                        Unit {suggestTarget?.unit_name}, ends{' '}
                        {suggestTarget?.ends_at}. Current rent:{' '}
                        {formatMoney(
                            suggestTarget?.rent_amount ?? null,
                            suggestTarget?.currency,
                        )}
                        .
                    </DialogDescription>

                    {suggestBusy ? (
                        <p className="text-sm text-muted-foreground">
                            Thinking…
                        </p>
                    ) : suggestion ? (
                        <div className="space-y-3">
                            <p className="text-2xl font-semibold tabular-nums">
                                {formatMoney(
                                    suggestion.suggested_rent,
                                    suggestTarget?.currency,
                                )}
                                <span className="ml-1 text-sm font-normal text-muted-foreground">
                                    / month
                                </span>
                            </p>
                            {suggestion.reasoning && (
                                <p className="text-sm text-muted-foreground">
                                    {suggestion.reasoning}
                                </p>
                            )}
                            <Button asChild size="sm" variant="outline">
                                <a
                                    href={`${draftingPage().url}?${new URLSearchParams(
                                        {
                                            type: 'lease_expiry_notice',
                                            tenant_name:
                                                suggestTarget?.occupant_name ??
                                                '',
                                            expiry_date:
                                                suggestTarget?.ends_at ?? '',
                                            suggested_rent: String(
                                                suggestion.suggested_rent,
                                            ),
                                        },
                                    )}`}
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
                        <OverlayFooter cancelClosesDialog cancelLabel="Close" />
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
