<?php

namespace App\Actions\Leases;

use App\Models\Lease;
use App\Models\LeaseAdditionalCharge;
use App\Models\Organization;
use App\Models\User;
use App\Services\Service;

/**
 * Adds a free-form extra to one lease. Kind-specific completeness lives
 * here, next to the lease: fixed bills every period, one-time bills once.
 */
class CreateLeaseAdditionalCharge extends Service
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Organization $organization, Lease $lease, array $data): LeaseAdditionalCharge
    {
        abort_unless($lease->organization_id === $organization->id, 404);

        abort_unless(in_array($data['kind'] ?? '', ['fixed', 'one_time'], true), 422, 'Unknown extra kind.');
        abort_unless(isset($data['amount']) && (float) $data['amount'] > 0, 422, 'An extra needs an amount above zero.');

        return $this->transaction(function () use ($actor, $organization, $lease, $data) {
            $extra = new LeaseAdditionalCharge([
                'organization_id' => $organization->id,
                'lease_id' => $lease->id,
                'name' => $data['name'],
                'kind' => $data['kind'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? $lease->currency ?? 'KES',
                'gl_code' => $data['gl_code'] ?? null,
                'is_active' => true,
                'created_by' => $actor->id,
            ]);
            $this->save($extra);

            return $extra;
        });
    }
}
