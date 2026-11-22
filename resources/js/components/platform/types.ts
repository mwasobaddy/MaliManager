import type { ComboPoint } from '@/components/dashboard/combo-chart';
import type { StatusTone } from '@/components/status-cell';

export type Kpis = {
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

export type OverviewAlert = {
    severity: StatusTone;
    text: string;
    /** Relative tenant URL behind the identifying noun, if actionable. */
    href?: string;
    /** Exact substring of `text` rendered as the link. */
    linkText?: string;
};

export type AttentionRow = {
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

export type Overview = {
    kpis: Kpis;
    income_trend: ComboPoint[];
    alerts: OverviewAlert[];
    attention: AttentionRow[];
    currency: string;
};

export type Growth = {
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
};

export type Payments = {
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
};

export type Adoption = {
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
};

export type Histories = {
    as_of: string | null;
    mrr: ComboPoint[];
    rent: ComboPoint[];
    active: number[];
    trials: number[];
    past_due: number[];
};

export type Operations = {
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
};

export type OrganizationRow = {
    id: number;
    name: string;
    slug: string;
    status: string;
    plan: string | null;
    domain: string | null;
    users: Array<{ id: number; name: string; email: string }>;
    created_at: string | null;
};

export type OrganizationsPagination = {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
};

export type DefaulterRow = {
    lease_id: number;
    occupant: string;
    unit: string | null;
    property: string;
    property_slug: string;
    outstanding: string;
    days_overdue: number;
    can_remind: boolean;
    /** True while the 24h reminder throttle holds for this lease. */
    remind_throttled: boolean;
    /** Human "2 hours ago", or null when never reminded. */
    last_reminded_ago: string | null;
};

export type AgingRow = {
    bucket: string;
    total: string;
    count: number;
};

export type Collection = {
    expected_mtd: string;
    collected_mtd: string;
    collection_rate: number | null;
    arrears_total: string;
    overdue_count: number;
};

export type UnitGridBuilding = {
    property: string;
    units: Array<{
        id: number;
        name: string;
        status: string;
        overdue: boolean;
        occupant: string | null;
        type: string | null;
        monthly_rent: string | null;
    }>;
};
