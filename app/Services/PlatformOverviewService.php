<?php

namespace App\Services;

use App\Enums\OrganizationStatus;
use App\Models\Audit;
use App\Models\LandParcel;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Occupant;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PlatformDailyMetric;
use App\Models\Property;
use App\Models\SubscriptionPayment;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Central-DB-only metrics for the platform dashboard: the five-second
 * "how are we doing" glance plus the tenants that need attention.
 *
 * Deliberately no tenant-database fan-out (units, rent GMV) and no
 * reconstructed history (MRR deltas, cohorts): at current scale every
 * figure here is a cheap central query, and anything needing per-tenant
 * scans or point-in-time history waits for the snapshot tables of phase 2
 * rather than arriving as a slow or invented number.
 *
 * Money is KES major units throughout: plan prices are stored that way
 * and every plan in the seed data bills in KES. A multi-currency future
 * needs per-currency buckets, not a summed total.
 */
class PlatformOverviewService extends Service
{
    private const CURRENCY = 'KES';

    private const TRIAL_ENDING_DAYS = 7;

    private const PAST_DUE_ALERT_DAYS = 14;

    /**
     * @return array{
     *     kpis: array{
     *         mrr: array{value: float, paying_organizations: int},
     *         active_organizations: array{value: int, on_trial: int},
     *         signups_this_month: array{value: int, last_month: int},
     *         past_due: array{value: int, exposure: float},
     *         collected_this_month: array{value: float, last_month: float, spark: list<float>},
     *         trials_ending: array{value: int, within_days: int},
     *     },
     *     income_trend: list<array{label: string, income: float}>,
     *     alerts: list<array{severity: string, text: string}>,
     *     attention: list<array{
     *         id: int, name: string, slug: string, status: string,
     *         plan: string|null, plan_price: float,
     *         period_ends_at: string|null, days_past_due: int|null,
     *         last_payment: array{amount: string, received_on: string}|null,
     *         reason: string,
     *     }>,
     *     currency: string,
     * }
     */
    public function payload(): array
    {
        $now = CarbonImmutable::now();
        $organizations = Organization::query()->with('plan:id,name,price')->get();

        $active = $organizations->where('status', OrganizationStatus::Active);
        $paying = $active->filter(fn (Organization $organization) => $this->isPaying($organization))->values();
        $pastDue = $active->filter(fn (Organization $organization) => $this->isPastDue($organization))->values();
        $trialsEnding = $active->filter(fn (Organization $organization) => $this->isTrialEnding($organization, $now))->values();
        $suspended = $organizations->where('status', OrganizationStatus::Suspended)->values();

        $monthStart = $now->startOfMonth();
        $lastMonthStart = $now->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = $now->subMonthNoOverflow()->endOfMonth();

        $collectedThisMonth = $this->collectedBetween($monthStart->toDateString(), $now->toDateString());
        $collectedLastMonth = $this->collectedBetween($lastMonthStart->toDateString(), $lastMonthEnd->toDateString());

        $attentionIds = $pastDue->pluck('id')
            ->concat($trialsEnding->pluck('id'))
            ->concat($suspended->pluck('id'))
            ->unique()->all();
        $lastPayments = $this->lastPayments($attentionIds);

        $attention = $pastDue
            ->concat($trialsEnding)
            ->concat($suspended)
            ->unique('id')
            ->map(fn (Organization $organization) => $this->attentionRow($organization, $now, $lastPayments))
            ->sortBy(fn (array $row) => [$row['reason'] === 'past_due' ? 0 : 1, -($row['days_past_due'] ?? 0)])
            ->values()
            ->all();

        return [
            'kpis' => [
                'mrr' => [
                    'value' => (float) $paying->sum(fn (Organization $organization) => $organization->plan?->price ?? 0),
                    'paying_organizations' => $paying->count(),
                ],
                'active_organizations' => [
                    'value' => $active->count(),
                    'on_trial' => $active->filter(fn (Organization $organization) => $organization->onTrial())->count(),
                ],
                'signups_this_month' => [
                    'value' => $organizations->filter(fn (Organization $organization) => $organization->created_at >= $monthStart)->count(),
                    'last_month' => $organizations->filter(fn (Organization $organization) => $organization->created_at >= $lastMonthStart && $organization->created_at < $monthStart)->count(),
                ],
                'past_due' => [
                    'value' => $pastDue->count(),
                    'exposure' => (float) $pastDue->sum(fn (Organization $organization) => $organization->plan?->price ?? 0),
                ],
                'collected_this_month' => [
                    'value' => $collectedThisMonth,
                    'last_month' => $collectedLastMonth,
                    'spark' => $this->monthlySpark($now),
                ],
                'trials_ending' => [
                    'value' => $trialsEnding->count(),
                    'within_days' => self::TRIAL_ENDING_DAYS,
                ],
            ],
            'income_trend' => $this->incomeTrend($now),
            'alerts' => $this->alerts($pastDue, $trialsEnding, $suspended, $now),
            'attention' => $attention,
            'currency' => self::CURRENCY,
        ];
    }

    private function isPaying(Organization $organization): bool
    {
        return $organization->plan !== null
            && $organization->plan->price > 0
            && ($organization->onTrial() || $organization->hasActiveSubscription());
    }

    /**
     * Confirmed onto a paid plan but neither trial nor subscription covers
     * today. Needs plan_confirmed_at: an org that never picked a plan is
     * on the free tier, not past due.
     */
    private function isPastDue(Organization $organization): bool
    {
        return $organization->plan_confirmed_at !== null
            && $organization->plan !== null
            && $organization->plan->price > 0
            && ! $organization->onTrial()
            && ! $organization->hasActiveSubscription();
    }

    private function isTrialEnding(Organization $organization, CarbonImmutable $now): bool
    {
        return $organization->onTrial()
            && $organization->trial_ends_at <= $now->addDays(self::TRIAL_ENDING_DAYS);
    }

    /**
     * Days since coverage lapsed, measured from whichever of the trial or
     * subscription period ended later.
     */
    private function daysPastDue(Organization $organization, CarbonImmutable $now): ?int
    {
        $reference = collect([$organization->trial_ends_at, $organization->subscription_ends_at])
            ->filter()
            ->max();

        return $reference ? (int) $now->startOfDay()->diffInDays($reference->startOfDay(), true) : null;
    }

    private function collectedBetween(string $from, string $to): float
    {
        return (float) SubscriptionPayment::query()
            ->whereDate('received_on', '>=', $from)
            ->whereDate('received_on', '<=', $to)
            ->sum('amount');
    }

    /**
     * @return list<float>
     */
    private function monthlySpark(CarbonImmutable $now): array
    {
        $spark = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = $now->subMonthsNoOverflow($i)->startOfMonth();
            $end = $i === 0 ? $now : $start->endOfMonth();
            $spark[] = $this->collectedBetween($start->toDateString(), $end->toDateString());
        }

        return $spark;
    }

    /**
     * @return list<array{label: string, income: float}>
     */
    private function incomeTrend(CarbonImmutable $now): array
    {
        $trend = [];

        for ($i = 11; $i >= 0; $i--) {
            $start = $now->subMonthsNoOverflow($i)->startOfMonth();
            $end = $i === 0 ? $now : $start->endOfMonth();
            $trend[] = [
                'label' => $start->format('M'),
                'income' => $this->collectedBetween($start->toDateString(), $end->toDateString()),
            ];
        }

        return $trend;
    }

    /**
     * @return array<int, array{amount: string, received_on: string}>
     */
    private function lastPayments(array $organizationIds): array
    {
        if ($organizationIds === []) {
            return [];
        }

        return SubscriptionPayment::query()
            ->whereIn('organization_id', $organizationIds)
            ->orderByDesc('received_on')
            ->get(['organization_id', 'amount', 'received_on'])
            ->unique('organization_id')
            ->mapWithKeys(fn (SubscriptionPayment $payment) => [
                $payment->organization_id => [
                    'amount' => (string) $payment->amount,
                    'received_on' => $payment->received_on->toDateString(),
                ],
            ])
            ->all();
    }

    /**
     * @param  array<int, array{amount: string, received_on: string}>  $lastPayments
     * @return array{id: int, name: string, slug: string, status: string, plan: string|null, plan_price: float, period_ends_at: string|null, days_past_due: int|null, last_payment: array{amount: string, received_on: string}|null, reason: string}
     */
    private function attentionRow(Organization $organization, CarbonImmutable $now, array $lastPayments): array
    {
        $pastDue = $this->isPastDue($organization);

        // The date the current or lapsed cover ends: trial while trialling,
        // otherwise whichever period ran out.
        $endsAt = $organization->onTrial()
            ? $organization->trial_ends_at
            : collect([$organization->trial_ends_at, $organization->subscription_ends_at])->filter()->max();

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'status' => $organization->status->value,
            'plan' => $organization->plan?->name,
            'plan_price' => (float) ($organization->plan?->price ?? 0),
            'period_ends_at' => $endsAt?->toDateString(),
            'days_past_due' => $pastDue ? $this->daysPastDue($organization, $now) : null,
            'last_payment' => $lastPayments[$organization->id] ?? null,
            'reason' => $pastDue ? 'past_due'
                : ($organization->status === OrganizationStatus::Suspended ? 'suspended' : 'trial_ending'),
        ];
    }

    /**
     * @return list<array{severity: string, text: string}>
     */
    private function alerts($pastDue, $trialsEnding, $suspended, CarbonImmutable $now): array
    {
        $alerts = [];

        foreach ($pastDue->filter(fn (Organization $organization) => ($this->daysPastDue($organization, $now) ?? 0) >= self::PAST_DUE_ALERT_DAYS) as $organization) {
            $days = $this->daysPastDue($organization, $now);
            $alerts[] = [
                'severity' => 'danger',
                'text' => "{$organization->name} — subscription lapsed {$days} days ago ({$organization->plan?->name}).",
            ];
        }

        foreach ($pastDue->filter(fn (Organization $organization) => ($this->daysPastDue($organization, $now) ?? 0) < self::PAST_DUE_ALERT_DAYS) as $organization) {
            $days = $this->daysPastDue($organization, $now);
            $alerts[] = [
                'severity' => 'warning',
                'text' => "{$organization->name} — subscription lapsed ".($days === null ? 'recently' : "{$days} days ago").'.',
            ];
        }

        if ($trialsEnding->isNotEmpty()) {
            $count = $trialsEnding->count();
            $alerts[] = [
                'severity' => 'warning',
                'text' => "{$count} trial".($count > 1 ? 's' : '').' end within '.self::TRIAL_ENDING_DAYS.' days.',
            ];
        }

        if ($suspended->isNotEmpty()) {
            $count = $suspended->count();
            $alerts[] = [
                'severity' => 'info',
                'text' => "{$count} suspended organization".($count > 1 ? 's' : '').'.',
            ];
        }

        if ($alerts === []) {
            $alerts[] = ['severity' => 'info', 'text' => 'Nothing needs attention.'];
        }

        return $alerts;
    }

    /**
     * Trial funnel: how far every organization got, from signup to first
     * rent to first subscription payment. Counts are current state, not
     * reconstructed history — a deleted property stops counting, which the
     * label does not hide because every stage reads as "has X now".
     *
     * @return list<array{stage: string, label: string, count: int, conversion: float|null}>
     */
    public function funnel(): array
    {
        $orgIds = Organization::query()->pluck('id');

        $propertyOrgIds = Property::query()->whereIn('organization_id', $orgIds)->distinct()->pluck('organization_id');

        $unitOrgIds = Unit::query()
            ->whereIn('property_id', Property::query()->whereIn('organization_id', $orgIds)->select('id'))
            ->with('property:id,organization_id')
            ->get()
            ->map(fn (Unit $unit) => $unit->property->organization_id)
            ->unique();

        $occupantOrgIds = Occupant::query()->whereIn('organization_id', $orgIds)->distinct()->pluck('organization_id');

        $rentOrgIds = Payment::query()
            ->whereIn('organization_id', $orgIds)
            ->where('status', 'received')
            ->distinct()->pluck('organization_id');

        $convertedOrgIds = SubscriptionPayment::query()->whereIn('organization_id', $orgIds)->distinct()->pluck('organization_id');

        $stages = [
            ['stage' => 'signed_up', 'label' => 'Signed up', 'ids' => $orgIds],
            ['stage' => 'first_property', 'label' => 'Added first property', 'ids' => $propertyOrgIds],
            ['stage' => 'first_unit', 'label' => 'Added first unit', 'ids' => $unitOrgIds],
            ['stage' => 'first_occupant', 'label' => 'Added first occupant', 'ids' => $occupantOrgIds],
            ['stage' => 'first_rent', 'label' => 'Collected first rent', 'ids' => $rentOrgIds],
            ['stage' => 'converted', 'label' => 'Paid for a plan', 'ids' => $convertedOrgIds],
        ];

        $previous = null;
        foreach ($stages as &$stage) {
            $stage['count'] = $stage['ids']->count();
            $stage['conversion'] = $previous > 0 ? round($stage['count'] / $previous * 100, 1) : null;
            $previous = $stage['count'];
            unset($stage['ids']);
        }
        unset($stage);

        return $stages;
    }

    /**
     * Cohort retention from subscription coverage: for each signup month
     * (trailing six), the share of the cohort holding payment cover in
     * each later month. Future months read null — unknowable, not zero.
     *
     * @return array{columns: list<string>, rows: list<array{cohort: string, size: int, cells: list<array{rate: float|null, retained: int}>}>}
     */
    public function cohorts(): array
    {
        $now = CarbonImmutable::now();
        $columns = [];
        for ($o = 0; $o <= 5; $o++) {
            $columns[] = $o === 0 ? 'M0' : "M+{$o}";
        }

        $rows = [];
        for ($c = 5; $c >= 0; $c--) {
            $start = $now->subMonthsNoOverflow($c)->startOfMonth();
            $ids = Organization::query()
                ->whereDate('created_at', '>=', $start->toDateString())
                ->whereDate('created_at', '<=', $start->endOfMonth()->toDateString())
                ->pluck('id');

            $cells = [];
            if ($ids->isNotEmpty()) {
                $payments = SubscriptionPayment::query()
                    ->whereIn('organization_id', $ids)
                    ->get(['organization_id', 'period_start', 'period_end']);
            }

            for ($o = 0; $o <= 5; $o++) {
                $monthStart = $start->addMonthsNoOverflow($o)->startOfMonth();

                if ($monthStart->greaterThan($now->startOfMonth())) {
                    $cells[] = ['rate' => null, 'retained' => 0];

                    continue;
                }

                $monthEnd = min($monthStart->endOfMonth(), $now);

                $retained = isset($payments)
                    ? $payments->filter(fn ($payment) => $payment->period_start <= $monthEnd->toDateString()
                        && ($payment->period_end === null || $payment->period_end >= $monthStart->toDateString()))
                        ->pluck('organization_id')->unique()->count()
                    : 0;

                $cells[] = [
                    'rate' => $ids->isNotEmpty() ? round($retained / $ids->count() * 100, 1) : null,
                    'retained' => $retained,
                ];
            }

            $rows[] = [
                'cohort' => $start->format('M Y'),
                'size' => $ids->count(),
                'cells' => $cells,
            ];
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * Gateway round-trips from PaymentAttempt: daily volumes, success
     * share, and confirmation latency. Pending attempts are still in
     * flight, so they count toward volume but never toward the rate.
     *
     * @return array{
     *     daily: list<array{label: string, attempts: int, success_rate: float|null, latency_p50_seconds: int|null}>,
     *     latency_this_week: int|null,
     *     latency_last_week: int|null,
     *     failures: list<array{organization: string, gateway: string, amount: string, reason: string, age: string}>,
     * }
     */
    public function paymentPerformance(): array
    {
        $now = CarbonImmutable::now();
        $daily = [];

        for ($i = 29; $i >= 0; $i--) {
            $day = $now->subDays($i)->toDateString();
            $attempts = PaymentAttempt::query()->whereDate('created_at', $day)->get(['status', 'created_at', 'updated_at']);

            $resolved = $attempts->filter(fn (PaymentAttempt $attempt) => $attempt->status !== 'pending');
            $succeeded = $resolved->filter(fn (PaymentAttempt $attempt) => $attempt->status === 'succeeded')->count();

            $latencies = $resolved
                ->map(fn (PaymentAttempt $attempt) => $attempt->updated_at->getTimestamp() - $attempt->created_at->getTimestamp())
                ->sort()->values();

            $daily[] = [
                'label' => CarbonImmutable::parse($day)->format('M d'),
                'attempts' => $attempts->count(),
                'success_rate' => $resolved->isNotEmpty() ? round($succeeded / $resolved->count() * 100, 1) : null,
                'latency_p50_seconds' => $this->median($latencies),
            ];
        }

        $latency = function (int $fromDaysAgo, int $toDaysAgo) use ($now): ?int {
            $seconds = PaymentAttempt::query()
                ->whereIn('status', ['succeeded', 'failed'])
                ->whereDate('created_at', '>=', $now->subDays($fromDaysAgo)->toDateString())
                ->whereDate('created_at', '<', $now->subDays($toDaysAgo)->toDateString())
                ->get(['created_at', 'updated_at'])
                ->map(fn (PaymentAttempt $attempt) => $attempt->updated_at->getTimestamp() - $attempt->created_at->getTimestamp())
                ->sort()->values();

            return $this->median($seconds);
        };

        $failures = PaymentAttempt::query()
            ->with('organization:id,name')
            ->where('status', 'failed')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (PaymentAttempt $attempt) => [
                'organization' => $attempt->organization?->name ?? 'Unknown',
                'gateway' => $attempt->gateway,
                'amount' => (string) $attempt->amount,
                'reason' => $attempt->meta['result_description'] ?? 'Failed',
                'age' => $attempt->created_at->diffForHumans(),
            ])
            ->all();

        return [
            'daily' => $daily,
            'latency_this_week' => $latency(7, 0),
            'latency_last_week' => $latency(14, 7),
            'failures' => $failures,
        ];
    }

    /**
     * Live adoption snapshot: totals plus the share of active organizations
     * actually using each module. Point-in-time by design; trends come
     * from histories(), not from here.
     *
     * @return array{
     *     properties_total: int,
     *     units_total: int,
     *     rent_month_to_date: float,
     *     maintenance_open: int,
     *     features: list<array{key: string, label: string, organizations: int, percent: float}>,
     * }
     */
    public function adoption(): array
    {
        $activeIds = Organization::query()->where('status', OrganizationStatus::Active)->pluck('id');
        $active = $activeIds->count();

        $propertyIds = Property::query()->whereIn('organization_id', $activeIds)->pluck('id');

        $featureCounts = [
            ['key' => 'leases', 'label' => 'Leases', 'ids' => Lease::query()->whereIn('organization_id', $activeIds)->distinct()->pluck('organization_id')],
            ['key' => 'maintenance', 'label' => 'Maintenance', 'ids' => MaintenanceRequest::query()->whereIn('organization_id', $activeIds)->distinct()->pluck('organization_id')],
            ['key' => 'land', 'label' => 'Land parcels', 'ids' => LandParcel::query()->whereIn('organization_id', $activeIds)->distinct()->pluck('organization_id')],
            ['key' => 'mpesa_rent', 'label' => 'M-Pesa rent', 'ids' => Payment::query()->whereIn('organization_id', $activeIds)->where('status', 'received')->where('method', 'mpesa')->distinct()->pluck('organization_id')],
        ];

        $now = CarbonImmutable::now();

        return [
            'properties_total' => $propertyIds->count(),
            'units_total' => $propertyIds->isEmpty() ? 0 : Unit::query()->whereIn('property_id', $propertyIds)->count(),
            'rent_month_to_date' => (float) Payment::query()
                ->whereIn('organization_id', $activeIds)
                ->where('status', 'received')
                ->whereDate('paid_on', '>=', $now->startOfMonth()->toDateString())
                ->sum('amount'),
            'maintenance_open' => MaintenanceRequest::query()
                ->whereIn('organization_id', $activeIds)
                ->whereIn('status', ['opened', 'assigned', 'in_progress'])
                ->count(),
            'features' => collect($featureCounts)->map(fn (array $feature) => [
                'key' => $feature['key'],
                'label' => $feature['label'],
                'organizations' => $feature['ids']->count(),
                'percent' => $active > 0 ? round($feature['ids']->count() / $active * 100, 1) : 0,
            ])->all(),
        ];
    }

    /**
     * Trend lines from the snapshot tables. Empty until metrics:snapshot
     * has run at least once — the frontend says so instead of flatlining.
     *
     * @return array{as_of: string|null, mrr: list<array{label: string, mrr: float}>, rent: list<array{label: string, rent: float}>}
     */
    public function histories(): array
    {
        $rows = PlatformDailyMetric::query()
            ->orderBy('captured_on')
            ->take(30)
            ->get(['captured_on', 'mrr', 'rent_month_to_date']);

        return [
            'as_of' => $rows->isNotEmpty() ? $rows->last()->captured_on->toDateString() : null,
            'mrr' => $rows->map(fn (PlatformDailyMetric $row) => [
                'label' => $row->captured_on->format('M d'),
                'mrr' => (float) $row->mrr,
            ])->all(),
            'rent' => $rows->map(fn (PlatformDailyMetric $row) => [
                'label' => $row->captured_on->format('M d'),
                'rent' => (float) $row->rent_month_to_date,
            ])->all(),
        ];
    }

    /**
     * Operations signals: failed jobs, recent audit activity, and which
     * integrations are configured. Integration tiles deliberately read
     * "configured", never "healthy" — presence of a key proves nothing
     * about the vendor being up.
     *
     * @return array{
     *     failed_jobs: array{count: int, recent: list<array{queue: string, age: string}>},
     *     audit: list<array{text: string, age: string}>,
     *     integrations: list<array{key: string, label: string, detail: string, configured: bool}>,
     * }
     */
    public function operations(): array
    {
        $failedCount = DB::table('failed_jobs')->count();

        $recentFailed = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(5)
            ->get(['queue', 'failed_at'])
            ->map(fn (object $job) => [
                'queue' => (string) $job->queue,
                'age' => CarbonImmutable::parse($job->failed_at)->diffForHumans(),
            ])
            ->all();

        $audit = Audit::query()
            ->latest()
            ->limit(8)
            ->get(['log_name', 'description', 'created_at'])
            ->map(fn (Audit $entry) => [
                'text' => "[{$entry->log_name}] {$entry->description}",
                'age' => $entry->created_at->diffForHumans(),
            ])
            ->all();

        $queue = (string) config('queue.default', 'sync');
        $mailer = (string) config('mail.default', '');

        return [
            'failed_jobs' => ['count' => $failedCount, 'recent' => $recentFailed],
            'audit' => $audit,
            'integrations' => [
                [
                    'key' => 'queue',
                    'label' => 'Queue workers',
                    'detail' => $queue === 'sync' ? 'sync (no workers)' : $queue,
                    'configured' => $queue !== 'sync',
                ],
                [
                    'key' => 'mail',
                    'label' => 'Mail',
                    'detail' => $mailer === '' ? 'not set' : $mailer,
                    'configured' => $mailer !== '',
                ],
                [
                    'key' => 'sms',
                    'label' => 'SMS gateway',
                    'detail' => (string) config('services.africastalking.username', '') === '' ? 'no username' : 'account set',
                    'configured' => (string) config('services.africastalking.username', '') !== '',
                ],
                [
                    'key' => 'mpesa',
                    'label' => 'M-Pesa Daraja',
                    'detail' => (string) config('services.mpesa.shortcode', '') === '' ? 'no shortcode' : 'shortcode set',
                    'configured' => (string) config('services.mpesa.shortcode', '') !== '',
                ],
            ],
        ];
    }

    /**
     * @param  Collection<int, int>  $sorted
     */
    private function median($sorted): ?int
    {
        if ($sorted->isEmpty()) {
            return null;
        }

        $count = $sorted->count();
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? (int) $sorted[$middle]
            : (int) round(($sorted[$middle - 1] + $sorted[$middle]) / 2);
    }
}
