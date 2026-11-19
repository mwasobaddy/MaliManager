import { Form, Head, Link, router } from '@inertiajs/react';
import { Building2, Shield } from 'lucide-react';
import { useState } from 'react';
import { ComboChart } from '@/components/dashboard/combo-chart';
import type { ComboPoint } from '@/components/dashboard/combo-chart';
import { StatusIcon } from '@/components/status-cell';
import type { StatusTone } from '@/components/status-cell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboard as platformDashboard } from '@/routes';
import { impersonate } from '@/routes/platform';
import * as orgRoutes from '@/routes/platform/organizations';
import { formatMoney } from '@/lib/format-money';
import { cn } from '@/lib/utils';

type Organization = {
    id: number;
    name: string;
    slug: string;
    status: string;
    plan: string | null;
    domain: string | null;
    users: Array<{ id: number; name: string; email: string }>;
    created_at: string | null;
};

type Kpis = {
    mrr: { value: number; paying_organizations: number };
    active_organizations: { value: number; on_trial: number };
    signups_this_month: { value: number; last_month: number };
    past_due: { value: number; exposure: number };
    collected_this_month: {
        value: number;
        last_month: number;
        spark: number[];
    };
    trials_ending: { value: number; within_days: number };
};

type OverviewAlert = {
    severity: StatusTone;
    text: string;
};

type AttentionRow = {
    id: number;
    name: string;
    slug: string;
    status: string;
    plan: string | null;
    plan_price: number;
    period_ends_at: string | null;
    days_past_due: number | null;
    last_payment: { amount: string; received_on: string } | null;
    reason: 'past_due' | 'trial_ending' | 'suspended';
};

type Overview = {
    kpis: Kpis;
    income_trend: ComboPoint[];
    alerts: OverviewAlert[];
    attention: AttentionRow[];
    currency: string;
};

type Props = {
    organizations: Organization[];
    organizations_pagination: {
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    overview: Overview | null;
    growth: {
        funnel: Array<{
            stage: string;
            label: string;
            count: number;
            conversion: number | null;
        }>;
        cohorts: {
            columns: string[];
            rows: Array<{
                cohort: string;
                size: number;
                cells: Array<{ rate: number | null; retained: number }>;
            }>;
        };
    } | null;
    payments: {
        daily: Array<{
            label: string;
            attempts: number;
            success_rate: number | null;
            latency_p50_seconds: number | null;
        }>;
        latency_this_week: number | null;
        latency_last_week: number | null;
        failures: Array<{
            organization: string;
            gateway: string;
            amount: string;
            reason: string;
            age: string;
        }>;
    } | null;
    adoption: {
        properties_total: number;
        units_total: number;
        rent_month_to_date: number;
        maintenance_open: number;
        features: Array<{
            key: string;
            label: string;
            organizations: number;
            percent: number;
        }>;
    } | null;
    histories: {
        as_of: string | null;
        mrr: ComboPoint[];
        rent: ComboPoint[];
        active: number[];
        trials: number[];
        past_due: number[];
    } | null;
    operations: {
        failed_jobs: {
            count: number;
            recent: Array<{ queue: string; age: string }>;
        };
        audit: Array<{ text: string; age: string }>;
        integrations: Array<{
            key: string;
            label: string;
            detail: string;
            configured: boolean;
        }>;
    } | null;
};

type DashboardTab = 'overview' | 'growth' | 'payments' | 'adoption';

const REASON_LABEL: Record<AttentionRow['reason'], string> = {
    past_due: 'Past due',
    trial_ending: 'Trial ending',
    suspended: 'Suspended',
};

const REASON_BADGE: Record<
    AttentionRow['reason'],
    'danger' | 'warning' | 'neutral'
> = {
    past_due: 'danger',
    trial_ending: 'warning',
    suspended: 'neutral',
};

/**
 * Six-point sparkline for the one KPI with honest history (collected
 * income). Decorative: the delta text next to it carries the meaning, so
 * screen readers and greyscale lose nothing.
 */
function Sparkline({ points }: { points: number[] }) {
    if (points.length < 2) {
        return null;
    }

    const width = 96;
    const height = 28;
    const max = Math.max(...points, 1);
    const step = width / (points.length - 1);
    const path = points
        .map(
            (point, index) =>
                `${index === 0 ? 'M' : 'L'}${(index * step).toFixed(1)},${(height - 2 - (point / max) * (height - 4)).toFixed(1)}`,
        )
        .join(' ');

    return (
        <svg
            viewBox={`0 0 ${width} ${height}`}
            className="h-7 w-24"
            aria-hidden
        >
            <path
                d={path}
                fill="none"
                stroke="var(--chart-1)"
                strokeWidth="2"
                strokeLinecap="round"
            />
        </svg>
    );
}

function KpiCard({
    label,
    value,
    sub,
    alert,
    spark,
}: {
    label: string;
    value: string;
    sub: string;
    alert?: boolean;
    spark?: number[];
}) {
    return (
        <Card>
            <CardContent className="flex flex-col gap-1 pt-5">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p
                    className={cn(
                        'text-2xl font-semibold tracking-tight tabular-nums',
                        alert && 'text-status-danger',
                    )}
                >
                    {value}
                </p>
                <div className="flex items-end justify-between gap-2">
                    <p className="text-xs text-muted-foreground tabular-nums">
                        {sub}
                    </p>
                    {spark && <Sparkline points={spark} />}
                </div>
            </CardContent>
        </Card>
    );
}

/**
 * Deliberately boring: flat rows with a status glyph and plain text. An
 * alert that shares the promo-card language (gradient, glow, illustration)
 * trains the reader to dismiss it like marketing.
 */
function AlertsPanel({ alerts }: { alerts: OverviewAlert[] }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Needs attention
                </CardTitle>
                <CardDescription>
                    Plain text on purpose — actionable, not decorative.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ul className="divide-y divide-border">
                    {alerts.map((alert) => (
                        <li
                            key={alert.text}
                            className="flex items-start gap-2.5 py-2.5 text-sm first:pt-0 last:pb-0"
                        >
                            <StatusIcon
                                tone={alert.severity}
                                label={alert.severity}
                                className="mt-0.5 shrink-0"
                            />
                            <span>{alert.text}</span>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

function AttentionTable({ rows }: { rows: AttentionRow[] }) {
    if (rows.length === 0) {
        return (
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-base font-medium">
                        Tenants to watch
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        Nothing needs attention.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Tenants to watch
                </CardTitle>
                <CardDescription>
                    Past-due, trials about to lapse, and suspended accounts
                    first — the full list is below.
                </CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto px-0">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-y text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <th className="px-4 py-2 font-medium">
                                Organization
                            </th>
                            <th className="px-4 py-2 font-medium">Reason</th>
                            <th className="px-4 py-2 font-medium">Plan</th>
                            <th className="px-4 py-2 font-medium">
                                Last payment
                            </th>
                            <th className="px-4 py-2 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-b last:border-0">
                                <td className="px-4 py-2.5">
                                    <p className="font-medium">{row.name}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {row.slug} ·{' '}
                                        {row.days_past_due !== null
                                            ? `${row.days_past_due}d past due`
                                            : row.period_ends_at
                                              ? `cover ends ${row.period_ends_at}`
                                              : row.status}
                                    </p>
                                </td>
                                <td className="px-4 py-2.5">
                                    <Badge variant={REASON_BADGE[row.reason]}>
                                        {REASON_LABEL[row.reason]}
                                    </Badge>
                                </td>
                                <td className="px-4 py-2.5 tabular-nums">
                                    {row.plan ?? 'No plan'}
                                </td>
                                <td className="px-4 py-2.5 text-muted-foreground tabular-nums">
                                    {row.last_payment
                                        ? `${row.last_payment.amount} · ${row.last_payment.received_on}`
                                        : 'Never paid'}
                                </td>
                                <td className="px-4 py-2.5">
                                    <div className="flex justify-end gap-2">
                                        <Form
                                            method="post"
                                            action={orgRoutes.impersonate(
                                                row.id,
                                            )}
                                            as="button"
                                            disabled={
                                                row.status === 'suspended'
                                            }
                                            title={
                                                row.status === 'suspended'
                                                    ? 'Suspended organizations cannot be impersonated'
                                                    : `Log in as ${row.name} owner`
                                            }
                                        >
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    row.status === 'suspended'
                                                }
                                            >
                                                Impersonate
                                            </Button>
                                        </Form>
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="sm"
                                        >
                                            <Link href={orgRoutes.index().url}>
                                                Manage
                                            </Link>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}

function formatLatency(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    if (seconds < 60) {
        return `${seconds}s`;
    }

    const minutes = Math.floor(seconds / 60);

    return `${minutes}m ${seconds % 60}s`;
}

/**
 * Trial funnel as horizontal bars with the stage-to-stage conversion in
 * text. One brand-neutral series: the drop-off numbers are the story, and
 * a rainbow would imply categories that are not there.
 */
function FunnelChart({
    stages,
}: {
    stages: NonNullable<Props['growth']>['funnel'];
}) {
    const first = stages[0]?.count ?? 0;

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Trial funnel
                </CardTitle>
                <CardDescription>
                    Current state per stage — a deleted property stops counting.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {stages.map((stage) => (
                    <div key={stage.stage} className="flex flex-col gap-1">
                        <div className="flex items-baseline justify-between gap-2 text-sm">
                            <span className="font-medium">{stage.label}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {stage.count}
                                {stage.conversion !== null &&
                                    ` · ${stage.conversion}% carry-over`}
                            </span>
                        </div>
                        <div
                            className="h-2.5 overflow-hidden rounded-full bg-muted"
                            role="img"
                            aria-label={`${stage.label}: ${stage.count} organizations${stage.conversion !== null ? `, ${stage.conversion}% carry-over` : ''}`}
                        >
                            <div
                                className="h-full rounded-full bg-chart-1"
                                style={{
                                    width: `${first > 0 ? Math.max((stage.count / first) * 100, stage.count > 0 ? 2 : 0) : 0}%`,
                                }}
                            />
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

/**
 * Cohort retention as a plain table, not a chart library heatmap: exact
 * values survive screen readers and greyscale, and no new dependency is
 * needed. Future months read as empty, never as zero.
 */
function CohortHeatmap({
    cohorts,
}: {
    cohorts: NonNullable<Props['growth']>['cohorts'];
}) {
    const tint = (rate: number | null): string => {
        if (rate === null) {
            return 'bg-muted/40 text-muted-foreground';
        }

        if (rate >= 90) {
            return 'bg-brand-accent/80 text-white';
        }

        if (rate >= 70) {
            return 'bg-brand-accent/60 text-white';
        }

        if (rate >= 50) {
            return 'bg-brand-accent/40 text-foreground';
        }

        if (rate >= 25) {
            return 'bg-brand-accent/20 text-foreground';
        }

        return 'bg-brand-accent/10 text-foreground';
    };

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Cohort retention
                </CardTitle>
                <CardDescription>
                    Share of each signup month holding subscription cover in
                    later months.
                </CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto px-0">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-y text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <th className="px-4 py-2 font-medium">Cohort</th>
                            <th className="px-4 py-2 font-medium">Size</th>
                            {cohorts.columns.map((column) => (
                                <th
                                    key={column}
                                    className="px-4 py-2 text-center font-medium"
                                >
                                    {column}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {cohorts.rows.map((row) => (
                            <tr
                                key={row.cohort}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-2 font-medium whitespace-nowrap">
                                    {row.cohort}
                                </td>
                                <td className="px-4 py-2 tabular-nums">
                                    {row.size}
                                </td>
                                {row.cells.map((cell, index) => (
                                    <td
                                        key={`${row.cohort}-${cohorts.columns[index]}`}
                                        className="px-2 py-1 text-center"
                                    >
                                        <span
                                            className={cn(
                                                'inline-block min-w-14 rounded-md px-2 py-1 text-xs font-medium tabular-nums',
                                                tint(cell.rate),
                                            )}
                                            title={
                                                cell.rate === null
                                                    ? 'Not yet knowable'
                                                    : `${cell.retained} of ${row.size} retained (${cell.rate}%)`
                                            }
                                        >
                                            {cell.rate === null
                                                ? '—'
                                                : `${cell.rate}%`}
                                        </span>
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}

function FailuresTable({
    failures,
    currency,
}: {
    failures: NonNullable<Props['payments']>['failures'];
    currency: string;
}) {
    if (failures.length === 0) {
        return (
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-base font-medium">
                        Recent failures
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        No failed gateway attempts on record.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Recent failures
                </CardTitle>
                <CardDescription>
                    Failed subscription payment attempts, newest first.
                </CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto px-0">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-y text-left text-xs tracking-wide text-muted-foreground uppercase">
                            <th className="px-4 py-2 font-medium">
                                Organization
                            </th>
                            <th className="px-4 py-2 font-medium">Gateway</th>
                            <th className="px-4 py-2 font-medium">Amount</th>
                            <th className="px-4 py-2 font-medium">Reason</th>
                            <th className="px-4 py-2 font-medium">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        {failures.map((failure, index) => (
                            <tr
                                key={`${failure.organization}-${failure.age}-${index}`}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-2.5 font-medium">
                                    {failure.organization}
                                </td>
                                <td className="px-4 py-2.5">
                                    <Badge variant="neutral">
                                        {failure.gateway}
                                    </Badge>
                                </td>
                                <td className="px-4 py-2.5 tabular-nums">
                                    {formatMoney(failure.amount, currency)}
                                </td>
                                <td className="px-4 py-2.5 text-muted-foreground">
                                    {failure.reason}
                                </td>
                                <td className="px-4 py-2.5 whitespace-nowrap text-muted-foreground">
                                    {failure.age}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardContent>
        </Card>
    );
}

function AdoptionBars({
    features,
}: {
    features: NonNullable<Props['adoption']>['features'];
}) {
    const max = Math.max(...features.map((f) => f.organizations), 1);

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Feature adoption
                </CardTitle>
                <CardDescription>
                    Share of active organizations with real data in each module.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {features.map((feature) => (
                    <div key={feature.key} className="flex flex-col gap-1">
                        <div className="flex items-baseline justify-between gap-2 text-sm">
                            <span className="font-medium">{feature.label}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {feature.organizations} · {feature.percent}%
                            </span>
                        </div>
                        <div
                            className="h-2.5 overflow-hidden rounded-full bg-muted"
                            role="img"
                            aria-label={`${feature.label}: ${feature.organizations} organizations, ${feature.percent}%`}
                        >
                            <div
                                className="h-full rounded-full bg-chart-2"
                                style={{
                                    width: `${(feature.organizations / max) * 100}%`,
                                }}
                            />
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

/**
 * Pulsing wireframe shown while a deferred analytics group resolves.
 * Same card shell the content will land in, so arrival shifts nothing.
 * Decorative by design: the loaded figures carry the meaning.
 */
function SectionSkeleton({ blocks = 4 }: { blocks?: number }) {
    const widths = [92, 78, 86, 64, 88, 72, 80];

    return (
        <Card aria-hidden>
            <CardContent className="flex flex-col gap-3 pt-5">
                {Array.from({ length: blocks }, (_, index) => (
                    <div
                        key={index}
                        className="h-4 animate-pulse rounded bg-skeleton"
                        style={{
                            width: `${widths[index % widths.length]}%`,
                        }}
                    />
                ))}
            </CardContent>
        </Card>
    );
}

/**
 * Snapshot-sourced sparkline points: two or more snapshots or nothing.
 * A single point is not a trend, and month-to-date counters never spark
 * (they sawtooth on the 1st) — those stay delta text.
 */
function snapshotSpark(values?: number[]): number[] | undefined {
    return values !== undefined && values.length >= 2 ? values : undefined;
}

export default function PlatformDashboard({
    organizations,
    organizations_pagination: pagination,
    overview,
    growth,
    payments,
    adoption,
    histories,
    operations,
}: Props) {
    // Overview ships eager, and so do growth, adoption, histories and
    // operations: bounded cheap queries. Only payments resolves
    // deferred (its table grows forever), skeletonizing while undefined.
    // The controller sets every eager key together, so one check covers
    // them; null anywhere here means no access, not loading.
    const metrics =
        overview != null &&
        growth != null &&
        adoption != null &&
        histories != null &&
        operations != null
            ? { overview, growth, payments, adoption, histories, operations }
            : null;
    const kpis = metrics?.overview.kpis;
    const currency = metrics?.overview.currency ?? 'KES';
    const signupDelta =
        (kpis?.signups_this_month.value ?? 0) -
        (kpis?.signups_this_month.last_month ?? 0);
    const collectedDelta =
        (kpis?.collected_this_month.value ?? 0) -
        (kpis?.collected_this_month.last_month ?? 0);

    const goOrgPage = (page: number) => {
        router.get(
            platformDashboard({ query: { orgs_page: page } }).url,
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Platform" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center gap-3">
                    <div className="flex size-10 items-center justify-center rounded-xl bg-sidebar-primary/10">
                        <Shield className="size-5" />
                    </div>
                    <div>
                        <h1 className="text-xl font-semibold">Platform</h1>
                        <p className="text-sm text-muted-foreground">
                            Platform health at a glance — organizations and
                            impersonation below.
                        </p>
                    </div>
                </div>

                {metrics === null || kpis === undefined ? (
                    <p className="text-sm text-muted-foreground">
                        Platform metrics need the &apos;view advanced
                        metrics&apos; permission. Organization management below
                        is unaffected.
                    </p>
                ) : (
                    <Tabs
                        defaultValue="overview"
                        className="flex flex-col gap-6"
                    >
                        <TabsList className="self-start">
                            <TabsTrigger value="overview">Overview</TabsTrigger>
                            <TabsTrigger value="growth">Growth</TabsTrigger>
                            <TabsTrigger value="payments">Payments</TabsTrigger>
                            <TabsTrigger value="adoption">Adoption</TabsTrigger>
                            <TabsTrigger value="operations">
                                Operations
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent
                            value="overview"
                            className="flex flex-col gap-6"
                        >
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                <KpiCard
                                    label="MRR"
                                    value={formatMoney(
                                        kpis.mrr.value,
                                        currency,
                                    )}
                                    sub={`${kpis.mrr.paying_organizations} paying organizations`}
                                    spark={snapshotSpark(
                                        metrics.histories?.mrr.map((row) =>
                                            Number(row.mrr),
                                        ),
                                    )}
                                />
                                <KpiCard
                                    label="Active organizations"
                                    value={String(
                                        kpis.active_organizations.value,
                                    )}
                                    sub={`${kpis.active_organizations.on_trial} on trial`}
                                    spark={snapshotSpark(
                                        metrics.histories?.active,
                                    )}
                                />
                                <KpiCard
                                    label="New signups this month"
                                    value={String(
                                        kpis.signups_this_month.value,
                                    )}
                                    sub={
                                        signupDelta === 0
                                            ? `same as last month (${kpis.signups_this_month.last_month})`
                                            : `${signupDelta > 0 ? '+' : ''}${signupDelta} vs last month (${kpis.signups_this_month.last_month})`
                                    }
                                />
                                <KpiCard
                                    label="Past-due subscriptions"
                                    value={String(kpis.past_due.value)}
                                    sub={`${formatMoney(kpis.past_due.exposure, currency)} exposed`}
                                    alert={kpis.past_due.value > 0}
                                    spark={snapshotSpark(
                                        metrics.histories?.past_due,
                                    )}
                                />
                                <KpiCard
                                    label="Collected this month"
                                    value={formatMoney(
                                        kpis.collected_this_month.value,
                                        currency,
                                    )}
                                    sub={`${collectedDelta >= 0 ? '+' : ''}${formatMoney(collectedDelta, currency)} vs last month`}
                                    spark={kpis.collected_this_month.spark}
                                />
                                <KpiCard
                                    label="Trials ending soon"
                                    value={String(kpis.trials_ending.value)}
                                    sub={`within ${kpis.trials_ending.within_days} days`}
                                    spark={snapshotSpark(
                                        metrics.histories?.trials,
                                    )}
                                />
                            </div>

                            <div className="grid items-start gap-4 xl:grid-cols-5">
                                <div className="xl:col-span-3">
                                    <ComboChart
                                        title="Subscription income — trailing 12 months"
                                        data={metrics.overview.income_trend}
                                        series={[
                                            {
                                                key: 'income',
                                                label: 'Income',
                                                color: 'var(--chart-1)',
                                                type: 'bar',
                                            },
                                        ]}
                                    />
                                </div>
                                <div className="xl:col-span-2">
                                    <AlertsPanel
                                        alerts={metrics.overview.alerts}
                                    />
                                </div>
                            </div>

                            <AttentionTable rows={metrics.overview.attention} />
                        </TabsContent>

                        <TabsContent
                            value="growth"
                            className="flex flex-col gap-4"
                        >
                            <div className="grid items-start gap-4 xl:grid-cols-2">
                                <FunnelChart stages={metrics.growth.funnel} />
                                <CohortHeatmap
                                    cohorts={metrics.growth.cohorts}
                                />
                            </div>
                            {metrics.histories.as_of === null ? (
                                <Card>
                                    <CardContent className="py-8 text-center text-sm text-muted-foreground">
                                        MRR history starts with the first
                                        nightly snapshot — run{' '}
                                        <code className="rounded bg-muted px-1 py-0.5 text-xs">
                                            metrics:snapshot
                                        </code>{' '}
                                        or wait for tonight&apos;s run.
                                    </CardContent>
                                </Card>
                            ) : (
                                <ComboChart
                                    title={`MRR history (snapshots since ${metrics.histories.as_of})`}
                                    data={metrics.histories.mrr}
                                    series={[
                                        {
                                            key: 'mrr',
                                            label: 'MRR',
                                            color: 'var(--chart-1)',
                                            type: 'area',
                                        },
                                    ]}
                                />
                            )}
                        </TabsContent>

                        <TabsContent
                            value="payments"
                            className="flex flex-col gap-4"
                        >
                            {metrics.payments == null ? (
                                <>
                                    <SectionSkeleton blocks={6} />
                                    <SectionSkeleton blocks={3} />
                                </>
                            ) : (
                                <>
                                    <ComboChart
                                        title="Gateway attempts and success rate — trailing 30 days"
                                        data={metrics.payments.daily.map(
                                            (day) => ({
                                                label: day.label,
                                                attempts: day.attempts,
                                                ...(day.success_rate !== null
                                                    ? {
                                                          success_rate:
                                                              day.success_rate,
                                                      }
                                                    : {}),
                                            }),
                                        )}
                                        series={[
                                            {
                                                key: 'attempts',
                                                label: 'Attempts',
                                                color: 'var(--chart-1)',
                                                type: 'bar',
                                            },
                                            {
                                                key: 'success_rate',
                                                label: 'Success %',
                                                color: 'var(--chart-2)',
                                                type: 'line',
                                                axis: 'right',
                                            },
                                        ]}
                                    />
                                    <p className="text-sm text-muted-foreground">
                                        Median confirmation latency:{' '}
                                        {formatLatency(
                                            metrics.payments.latency_this_week,
                                        )}{' '}
                                        trailing 7 days vs{' '}
                                        {formatLatency(
                                            metrics.payments.latency_last_week,
                                        )}{' '}
                                        the week before. Pending attempts are
                                        still in flight and never count toward
                                        the rate.
                                    </p>
                                    <FailuresTable
                                        failures={metrics.payments.failures}
                                        currency={currency}
                                    />
                                </>
                            )}
                        </TabsContent>

                        <TabsContent
                            value="adoption"
                            className="flex flex-col gap-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <KpiCard
                                    label="Properties under management"
                                    value={String(
                                        metrics.adoption.properties_total,
                                    )}
                                    sub="across active organizations"
                                />
                                <KpiCard
                                    label="Units under management"
                                    value={String(metrics.adoption.units_total)}
                                    sub="across active organizations"
                                />
                                <KpiCard
                                    label="Rent collected this month"
                                    value={formatMoney(
                                        metrics.adoption.rent_month_to_date,
                                        currency,
                                    )}
                                    sub="received payments platform-wide"
                                />
                                <KpiCard
                                    label="Open maintenance"
                                    value={String(
                                        metrics.adoption.maintenance_open,
                                    )}
                                    sub="platform-wide right now"
                                />
                            </div>
                            <AdoptionBars
                                features={metrics.adoption.features}
                            />
                            {(metrics.histories?.rent.length ?? 0) > 0 && (
                                <ComboChart
                                    title="Rent collected trend (nightly snapshots)"
                                    data={metrics.histories?.rent ?? []}
                                    series={[
                                        {
                                            key: 'rent',
                                            label: 'Rent',
                                            color: 'var(--chart-2)',
                                            type: 'bar',
                                        },
                                    ]}
                                />
                            )}
                        </TabsContent>

                        <TabsContent
                            value="operations"
                            className="flex flex-col gap-4"
                        >
                            <div className="grid items-start gap-4 xl:grid-cols-2">
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-base font-medium">
                                            Integrations
                                        </CardTitle>
                                        <CardDescription>
                                            Configured means a credential exists
                                            — not that the vendor is up.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <ul className="divide-y divide-border">
                                            {metrics.operations.integrations.map(
                                                (integration) => (
                                                    <li
                                                        key={integration.key}
                                                        className="flex items-center gap-2.5 py-2.5 text-sm first:pt-0 last:pb-0"
                                                    >
                                                        <StatusIcon
                                                            tone={
                                                                integration.configured
                                                                    ? 'success'
                                                                    : 'muted'
                                                            }
                                                            label={
                                                                integration.configured
                                                                    ? 'configured'
                                                                    : 'not configured'
                                                            }
                                                            className="shrink-0"
                                                        />
                                                        <span className="font-medium">
                                                            {integration.label}
                                                        </span>
                                                        <span className="ml-auto text-muted-foreground">
                                                            {integration.detail}
                                                        </span>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-base font-medium">
                                            Failed jobs
                                        </CardTitle>
                                        <CardDescription>
                                            {metrics.operations.failed_jobs
                                                .count === 0
                                                ? 'Queue is clear.'
                                                : `${metrics.operations.failed_jobs.count} failed — inspect before retrying.`}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {metrics.operations.failed_jobs.recent
                                            .length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                None recorded.
                                            </p>
                                        ) : (
                                            <ul className="divide-y divide-border">
                                                {metrics.operations.failed_jobs.recent.map(
                                                    (job, index) => (
                                                        <li
                                                            key={`${job.queue}-${job.age}-${index}`}
                                                            className="flex items-center gap-2.5 py-2.5 text-sm first:pt-0 last:pb-0"
                                                        >
                                                            <StatusIcon
                                                                tone="danger"
                                                                label="failed"
                                                                className="shrink-0"
                                                            />
                                                            <span className="font-medium">
                                                                {job.queue}
                                                            </span>
                                                            <span className="ml-auto text-muted-foreground">
                                                                {job.age}
                                                            </span>
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        )}
                                    </CardContent>
                                </Card>
                            </div>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-base font-medium">
                                        Recent audit activity
                                    </CardTitle>
                                    <CardDescription>
                                        Latest platform log entries, newest
                                        first.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {metrics.operations.audit.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No audit entries yet.
                                        </p>
                                    ) : (
                                        <ul className="divide-y divide-border">
                                            {metrics.operations.audit.map(
                                                (entry, index) => (
                                                    <li
                                                        key={`${entry.text}-${entry.age}-${index}`}
                                                        className="flex items-baseline gap-2.5 py-2 text-sm first:pt-0 last:pb-0"
                                                    >
                                                        <span className="min-w-0 flex-1 truncate">
                                                            {entry.text}
                                                        </span>
                                                        <span className="shrink-0 text-xs whitespace-nowrap text-muted-foreground">
                                                            {entry.age}
                                                        </span>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>
                )}

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-base font-semibold">
                        All organizations
                    </h2>
                    <Link
                        href={orgRoutes.index().url}
                        className="text-sm font-medium text-accent-text hover:text-foreground"
                    >
                        Manage all
                    </Link>
                </div>

                {organizations.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            No organizations have been created yet.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4">
                        {organizations.map((organization) => (
                            <Card key={organization.id}>
                                <CardHeader>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <Building2 className="size-4 text-muted-foreground" />
                                            <CardTitle>
                                                {organization.name}
                                            </CardTitle>
                                            <Badge variant="primary">
                                                {organization.plan ?? 'No plan'}
                                            </Badge>
                                            <Badge
                                                variant={
                                                    organization.status ===
                                                    'active'
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            >
                                                {organization.status}
                                            </Badge>
                                        </div>
                                        <CardDescription>
                                            {organization.slug}
                                        </CardDescription>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {organization.users.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No members.
                                        </p>
                                    ) : (
                                        <div className="divide-y divide-border rounded-lg border">
                                            {organization.users.map((user) => (
                                                <div
                                                    key={user.id}
                                                    className="flex items-center justify-between gap-4 px-4 py-3"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">
                                                            {user.name}
                                                        </p>
                                                        <p className="truncate text-sm text-muted-foreground">
                                                            {user.email}
                                                        </p>
                                                    </div>
                                                    <Form
                                                        method="post"
                                                        action={impersonate([
                                                            organization.id,
                                                            user.id,
                                                        ])}
                                                        as="button"
                                                        disabled={
                                                            !organization.domain
                                                        }
                                                        title={
                                                            organization.domain
                                                                ? `Log in as ${user.name}`
                                                                : 'No domain configured'
                                                        }
                                                    >
                                                        <Button
                                                            type="submit"
                                                            variant="outline"
                                                            size="sm"
                                                            disabled={
                                                                !organization.domain
                                                            }
                                                        >
                                                            Impersonate
                                                        </Button>
                                                    </Form>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
                {pagination.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm text-muted-foreground tabular-nums">
                            Page {pagination.current_page} of{' '}
                            {pagination.last_page} · {pagination.total} total
                        </p>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={pagination.current_page <= 1}
                                onClick={() =>
                                    goOrgPage(pagination.current_page - 1)
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={
                                    pagination.current_page >=
                                    pagination.last_page
                                }
                                onClick={() =>
                                    goOrgPage(pagination.current_page + 1)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

PlatformDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Platform',
        },
    ],
};
