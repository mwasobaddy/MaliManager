<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendBillingReminders extends Command
{
    protected $signature = 'billing:remind {--organization= : Limit to one organization id}';

    protected $description = 'Remind owners whose trial is ending or lapsed, on each owner\'s preferred channel';

    public function handle(NotificationService $notifications): int
    {
        // Paid plan, trial started, no active subscription. Anything else
        // needs nothing: free plans never bill, subscribers already paid.
        $organizations = Organization::query()
            ->whereHas('plan', fn ($query) => $query->where('price', '>', 0))
            ->whereNotNull('trial_ends_at')
            ->where(function ($query) {
                $query->whereNull('subscription_ends_at')
                    ->orWhere('subscription_ends_at', '<', now());
            })
            ->when($this->option('organization'), fn ($query) => $query->whereKey((int) $this->option('organization')))
            ->get();

        foreach ($organizations as $organization) {
            $endsAt = $organization->trial_ends_at;
            $lapsed = $endsAt->isPast();
            $upcoming = ! $lapsed && $endsAt->lessThanOrEqualTo(now()->addDays(3));

            if (! $upcoming && ! $lapsed) {
                continue;
            }

            $owners = $organization->users()->wherePivot('is_owner', true)->get();

            foreach ($owners as $owner) {
                $body = $upcoming
                    ? "Your {$organization->plan->name} free trial for {$organization->name} ends on {$organization->trial_ends_at->toDateString()}. Pay to stay on {$organization->plan->name} without interruption."
                    : "Your {$organization->plan->name} trial for {$organization->name} ended on {$organization->trial_ends_at->toDateString()}. Pay now to restore full limits.";

                $notifications->sendBillingReminder(
                    $owner,
                    $organization->name,
                    $organization->plan->name,
                    $body,
                );
            }

            $this->info("Billing reminder sent for {$organization->name}.");
        }

        return self::SUCCESS;
    }
}
