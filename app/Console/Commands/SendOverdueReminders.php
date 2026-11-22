<?php

namespace App\Console\Commands;

use App\Actions\Leases\SendRentReminder;
use App\Models\Lease;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Automatic tenant nudges for leases the 1am flag job marked overdue. Only
 * leases overdue since before today qualify, so nobody gets a 3am message
 * for rent that fell due this morning. Every send still rides the shared
 * SendRentReminder: balance recheck, 24h throttle, channel routing and the
 * audit entry behave exactly like a manual tap, and per-lease failures
 * collect as skips. The causer is the organization's first owner — it is
 * their business the automation acts for.
 */
class SendOverdueReminders extends Command
{
    protected $signature = 'rent:remind-overdue';

    protected $description = 'Send automatic rent reminders for leases flagged overdue before today';

    /**
     * @var array<int, User|null>
     */
    private array $owners = [];

    public function handle(SendRentReminder $remind): int
    {
        $sent = 0;
        $skipped = 0;

        Lease::query()
            ->where('status', 'active')
            ->whereNotNull('overdue_since')
            ->whereDate('overdue_since', '<', now()->toDateString())
            ->orderBy('id')
            ->chunkById(200, function ($leases) use ($remind, &$sent, &$skipped): void {
                foreach ($leases as $lease) {
                    $actor = $this->ownerFor($lease->organization_id);

                    if ($actor === null) {
                        $skipped++;

                        continue;
                    }

                    try {
                        $remind->handle($lease, $actor);
                        $sent++;
                    } catch (\DomainException $e) {
                        $skipped++;
                    }
                }
            });

        $this->info("Overdue reminders: {$sent} sent, {$skipped} skipped.");

        return self::SUCCESS;
    }

    private function ownerFor(int $organizationId): ?User
    {
        if (! array_key_exists($organizationId, $this->owners)) {
            $this->owners[$organizationId] = User::query()
                ->whereHas('organizations', fn ($query) => $query
                    ->where('organizations.id', $organizationId)
                    ->where('organization_user.is_owner', true))
                ->orderBy('id')
                ->first();
        }

        return $this->owners[$organizationId];
    }
}
