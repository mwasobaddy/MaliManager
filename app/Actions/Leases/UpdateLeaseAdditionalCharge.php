<?php

namespace App\Actions\Leases;

use App\Models\Lease;
use App\Models\LeaseAdditionalCharge;
use App\Models\User;
use App\Services\Service;

/**
 * Renames, reprices, toggles or re-ledgers a lease extra. The kind is
 * immutable after creation for the same reason as utility charges: a
 * miscategorized extra is deleted and recreated instead of morphed.
 */
class UpdateLeaseAdditionalCharge extends Service
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(LeaseAdditionalCharge $extra, Lease $lease, User $actor, array $data): LeaseAdditionalCharge
    {
        abort_unless($extra->lease_id === $lease->id, 404);
        abort_unless(isset($data['amount']) && (float) $data['amount'] > 0, 422, 'An extra needs an amount above zero.');

        return $this->transaction(function () use ($extra, $data) {
            $extra->fill([
                'name' => $data['name'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? $extra->currency,
                'gl_code' => $data['gl_code'] ?? $extra->gl_code,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $extra->is_active,
            ])->save();

            return $extra->refresh();
        });
    }
}
