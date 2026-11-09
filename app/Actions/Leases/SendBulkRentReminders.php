<?php

namespace App\Actions\Leases;

use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use App\Services\Service;

/**
 * Nudges several overdue leases at once — the manual counterpart to the
 * AI broadcast. Every lease still goes through SendRentReminder, so the
 * balance check, 24h throttle, channel routing and activity entries
 * behave exactly like single sends; per-lease failures collect as skips
 * instead of aborting the batch.
 */
class SendBulkRentReminders extends Service
{
    public function __construct(private SendRentReminder $remind) {}

    /**
     * @param  array<int, int>  $leaseIds
     * @return array{sent: array<int, array{lease_id: int, channel: string, outstanding: string}>, skipped: array<int, array{lease_id: int, reason: string}>}
     */
    public function handle(Property $property, User $actor, array $leaseIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $leaseIds)));
        $leases = Lease::query()->whereIn('id', $ids)->get();

        // Ids from outside this property (or that no longer exist) are
        // tampering, not skips: refuse the whole batch.
        abort_if(
            $leases->count() !== count($ids)
            || $leases->contains(fn (Lease $lease): bool => $lease->property_id !== $property->id),
            403,
        );

        $sent = [];
        $skipped = [];

        foreach ($leases as $lease) {
            try {
                $sent[] = $this->remind->handle($lease, $actor);
            } catch (\DomainException $e) {
                $skipped[] = ['lease_id' => $lease->id, 'reason' => $e->getMessage()];
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }
}
