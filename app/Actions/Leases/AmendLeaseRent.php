<?php

namespace App\Actions\Leases;

use App\Models\Lease;
use App\Models\LeaseRentChange;
use App\Models\Organization;
use App\Models\User;
use App\Services\Service;
use Carbon\CarbonImmutable;

/**
 * Amends a lease's rent from a date: records the new rate in the rent
 * history (updating the row when one already takes effect that day) and
 * mirrors the latest rate onto the lease, which every display reads.
 * Earlier periods keep pricing at their own rate — arrears never rewrite
 * history, they just start accruing the new rate from the effective date.
 */
class AmendLeaseRent extends Service
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Organization $organization, Lease $lease, array $data): Lease
    {
        abort_unless($lease->organization_id === $organization->id, 404);
        abort_unless(isset($data['rent_amount']) && (float) $data['rent_amount'] > 0, 422, 'The new rent needs an amount above zero.');

        $startsAt = $lease->starts_at?->toImmutable()->startOfDay();
        abort_if($startsAt === null, 422, 'This lease has no start date — end it and create a new one instead.');

        $effective = isset($data['effective_date']) && $data['effective_date'] !== null && $data['effective_date'] !== ''
            ? CarbonImmutable::parse((string) $data['effective_date'])->startOfDay()
            : now()->toImmutable()->startOfDay();
        abort_if($effective->lessThan($startsAt), 422, 'The new rent cannot take effect before the lease started.');

        return $this->transaction(function () use ($actor, $organization, $lease, $data, $effective, $startsAt) {
            // Leases that predate rent history have no inception row: capture
            // the pre-amendment rate first, or every earlier period would
            // fall back to the new rent and rewrite history.
            if (! $lease->rentChanges()->exists()) {
                $lease->rentChanges()->create([
                    'organization_id' => $organization->id,
                    'effective_date' => $startsAt->toDateString(),
                    'rent_amount' => $lease->rent_amount ?? 0,
                    'created_by' => $actor->id,
                ]);
            }

            // Matched in PHP, not in SQL: date columns serialize with a time
            // component on some drivers, so `=` on the raw string misses.
            // Rows per lease are few, so one query covers them all.
            $row = $lease->rentChanges()->get()->first(
                fn (LeaseRentChange $change): bool => $change->effective_date->toDateString() === $effective->toDateString()
            );

            if ($row) {
                $row->fill([
                    'rent_amount' => $data['rent_amount'],
                    'created_by' => $actor->id,
                ])->save();
            } else {
                $lease->rentChanges()->create([
                    'organization_id' => $organization->id,
                    'effective_date' => $effective->toDateString(),
                    'rent_amount' => $data['rent_amount'],
                    'created_by' => $actor->id,
                ]);
            }

            // The relation orders ascending; clear it before taking the
            // latest — chained orders append, and ASC would win.
            $latest = $lease->rentChanges()->reorder()->orderByDesc('effective_date')->firstOrFail();
            $lease->fill(['rent_amount' => $latest->rent_amount])->save();

            return $lease->refresh();
        });
    }
}
