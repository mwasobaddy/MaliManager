<?php

namespace App\Actions\Leases;

use App\Models\Lease;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Services\Service;
use Illuminate\Support\Collection;

/**
 * Posts one extra to many leases at once — the fence levy, the parking
 * fee, the once-off charge every active tenancy owes. Ended leases are
 * history, not billable: they are skipped and counted, never touched.
 * Leases outside the property are rejected outright.
 *
 * @param  array<string, mixed>  $data
 * @return array{created: int, skipped: int}
 */
class BulkCreateLeaseAdditionalCharges extends Service
{
    public function handle(User $actor, Organization $organization, Property $property, array $data): array
    {
        $leaseIds = array_values(array_unique(array_map('intval', (array) ($data['lease_ids'] ?? []))));

        abort_if($leaseIds === [], 422, 'Choose at least one lease.');
        abort_unless(in_array($data['kind'] ?? '', ['fixed', 'one_time'], true), 422, 'Unknown extra kind.');
        abort_unless(isset($data['amount']) && (float) $data['amount'] > 0, 422, 'An extra needs an amount above zero.');

        /** @var Collection<int, Lease> $leases */
        $leases = Lease::query()
            ->whereIn('id', $leaseIds)
            ->get();

        abort_unless(
            $leases->every(fn (Lease $lease): bool => $lease->organization_id === $organization->id && $lease->property_id === $property->id),
            404
        );

        return $this->transaction(function () use ($actor, $organization, $leases, $data) {
            $created = 0;
            $skipped = 0;

            foreach ($leases as $lease) {
                if ($lease->status === 'ended') {
                    $skipped++;

                    continue;
                }

                $lease->additionalCharges()->create([
                    'organization_id' => $organization->id,
                    'name' => $data['name'],
                    'kind' => $data['kind'],
                    'amount' => $data['amount'],
                    'currency' => $data['currency'] ?? $lease->currency ?? 'KES',
                    'gl_code' => $data['gl_code'] ?? null,
                    'is_active' => true,
                    'created_by' => $actor->id,
                ]);
                $created++;
            }

            return ['created' => $created, 'skipped' => $skipped];
        });
    }
}
