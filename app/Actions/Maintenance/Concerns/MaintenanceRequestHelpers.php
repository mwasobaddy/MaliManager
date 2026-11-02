<?php

namespace App\Actions\Maintenance\Concerns;

use App\Enums\PlatformRole;
use App\Models\Lease;
use App\Models\User;

trait MaintenanceRequestHelpers
{
    /**
     * The active lease of the raiser matching the requested property/unit,
     * when the raiser is an occupant-side user.
     */
    private function activeLeaseFor(User $raiser, array $data): ?Lease
    {
        if (! $raiser->person_id) {
            return null;
        }

        return Lease::query()
            ->where('person_id', $raiser->person_id)
            ->where('status', 'active')
            ->whereNull('ends_at')
            ->when(! empty($data['lease_id']), fn ($query) => $query->whereKey((int) $data['lease_id']))
            ->when(! empty($data['property_id']), fn ($query) => $query->where('property_id', (int) $data['property_id']))
            ->first();
    }

    private function isStaffOf(User $user, int $organizationId): bool
    {
        return $user->organizations()->whereKey($organizationId)->exists()
            || $user->hasRole(PlatformRole::Admin->value);
    }
}
