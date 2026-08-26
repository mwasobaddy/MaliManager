<?php

namespace App\Console\Commands;

use App\Enums\AiFeature;
use App\Mail\OrgDigestMail;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\User;
use App\Support\Ai\AiGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Prism\Prism\Facades\Prism;

class SendWeeklyDigest extends Command
{
    protected $signature = 'ai:send-digest {--organization= : Limit to one organization id}';

    protected $description = 'Email a weekly portfolio digest (numbers + AI narrative) to owners and managers';

    public function handle(AiGateway $gateway): int
    {
        $since = now()->subDays(7);

        $organizations = Organization::query()
            ->when($this->option('organization'), fn ($query) => $query->whereKey((int) $this->option('organization')))
            ->get();

        foreach ($organizations as $organization) {
            $numbers = $this->weeklyNumbers($organization, $since);

            // Recipients: owners plus maintenance managers.
            $recipients = $this->recipientsFor($organization);

            if ($recipients->isEmpty()) {
                continue;
            }

            // AI narrative only when the organization has its own key.
            $narrative = null;
            $owner = $recipients->first(fn (User $user) => $user->isOwnerOf($organization));

            if ($owner) {
                $credential = $gateway->resolve($owner, AiFeature::AskData, $organization);

                if ($credential) {
                    $narrative = $this->narrative($gateway, $credential, $owner, $numbers);
                }
            }

            foreach ($recipients as $recipient) {
                Mail::to($recipient->email)->send(
                    new OrgDigestMail($organization, $numbers, $narrative),
                );
            }

            $this->info("Digest sent for {$organization->name} to {$recipients->count()} recipient(s).");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, int|float>
     */
    private function weeklyNumbers(Organization $organization, $since): array
    {
        $now = now();
        $weekAgo = $since;
        $prevStart = $since->copy()->subDays(7);

        $leasesThis = Lease::where('organization_id', $organization->id)
            ->where('starts_at', '>=', $weekAgo);
        $leasesPrev = Lease::where('organization_id', $organization->id)
            ->whereBetween('starts_at', [$prevStart, $weekAgo]);
        $ended = Lease::where('organization_id', $organization->id)
            ->where('ends_at', '>=', $weekAgo);

        $expenses = Expense::where('organization_id', $organization->id)
            ->where('spent_on', '>=', $weekAgo->toDateString());
        $expensesPrev = Expense::where('organization_id', $organization->id)
            ->whereBetween('spent_on', [$prevStart->toDateString(), $weekAgo->toDateString()]);

        $maintenance = MaintenanceRequest::where('organization_id', $organization->id)
            ->where('created_at', '>=', $weekAgo);
        $maintenancePrev = MaintenanceRequest::where('organization_id', $organization->id)
            ->whereBetween('created_at', [$prevStart, $weekAgo]);

        // Occupancy snapshot across all properties.
        $totalUnits = Unit::query()
            ->whereHas('property', fn ($query) => $query
                ->where('organization_id', $organization->id))
            ->count();
        $occupiedUnits = Unit::query()
            ->whereHas('property', fn ($query) => $query
                ->where('organization_id', $organization->id))
            ->where('status', 'occupied')
            ->count();

        // Expiring leases within 60 days.
        $expiring = Lease::where('organization_id', $organization->id)
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$now->toDateString(), $now->copy()->addDays(60)->toDateString()])
            ->count();

        return [
            'leases_started' => $leasesThis->count(),
            'leases_started_prev' => $leasesPrev->count(),
            'leases_ended' => $ended->count(),
            'expenses_total' => round((float) $expenses->sum('amount'), 2),
            'expenses_count' => $expenses->count(),
            'expenses_total_prev' => round((float) $expensesPrev->sum('amount'), 2),
            'maintenance_opened' => $maintenance->count(),
            'maintenance_opened_prev' => $maintenancePrev->count(),
            'maintenance_resolved' => (clone $maintenance)->where('status', 'resolved')->count(),
            'urgent_open' => MaintenanceRequest::where('organization_id', $organization->id)
                ->whereIn('status', ['opened', 'assigned', 'in_progress'])
                ->where('priority', 'urgent')
                ->count(),
            'units_total' => $totalUnits,
            'units_occupied' => $occupiedUnits,
            'expiring_leases_60d' => $expiring,
        ];
    }

    private function narrative(
        AiGateway $gateway,
        $credential,
        User $user,
        array $numbers,
    ): ?string {
        $startedAt = microtime(true);

        try {
            $response = Prism::text()
                ->using($credential->prismProvider(), $credential->model)
                ->usingProviderConfig($credential->requestConfig())
                ->withSystemPrompt(
                    'You write a short executive summary (3-4 sentences) of a weekly '
                    .'property management digest for an owner. The numbers include '
                    .'*_prev keys for LAST week — call out notable week-over-week changes, '
                    .'mention occupancy (units_occupied of units_total) and any leases '
                    .'expiring within 60 days. Flag anything concerning and suggest one '
                    .'action if warranted.',
                )
                ->withPrompt('This week\'s numbers (with *_prev for last week): '.json_encode($numbers))
                ->asText();

            $gateway->log(
                $credential,
                $user,
                AiFeature::AskData,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                durationMs: (int) ((microtime(true) - $startedAt) * 1000),
            );

            return $response->text;
        } catch (\Throwable $e) {
            $gateway->log(
                $credential,
                $user,
                AiFeature::AskData,
                status: 'error',
                error: $e->getMessage(),
            );

            return null;
        }
    }

    /**
     * Owners plus staff holding the maintenance.manage sub-permission.
     *
     * @return Collection<int, User>
     */
    private function recipientsFor(Organization $organization): Collection
    {
        return User::query()
            ->whereHas('organizations', fn ($query) => $query
                ->whereKey($organization->id)
                ->where('organization_user.status', 'active'))
            ->get()
            ->filter(function (User $user) use ($organization) {
                if ($user->isOwnerOf($organization)) {
                    return true;
                }

                $membership = $user->membershipFor($organization);

                return $membership?->sub_role_id
                    && $membership->subRole->subPermissions()
                        ->pluck('key')
                        ->contains('maintenance.manage');
            })
            ->values();
    }
}
