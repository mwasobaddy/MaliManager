<?php

namespace App\Actions\Maintenance;

use App\Actions\Maintenance\Concerns\MaintenanceRequestHelpers;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\Service;
use App\Support\TenancyContext;

class CreateMaintenanceRequest extends Service
{
    use MaintenanceRequestHelpers;

    public function handle(User $raiser, int $organizationId, array $data): MaintenanceRequest
    {
        return $this->transaction(function () use ($raiser, $organizationId, $data) {
            // Occupants raise against an active lease; the organization is
            // derived from that lease.
            $activeLease = $this->activeLeaseFor($raiser, $data);

            if ($activeLease) {
                $organizationId = $activeLease->organization_id;
            } else {
                abort_unless(
                    ! empty($data['property_id']) && $this->isStaffOf($raiser, $organizationId),
                    403,
                    'Only occupants with an active lease can raise requests.',
                );
            }

            $record = new MaintenanceRequest([
                'tenant_id' => TenancyContext::tenantId(),
                'organization_id' => $organizationId,
                'property_id' => $activeLease?->property_id ?? ($data['property_id'] ?? null),
                'unit_id' => $activeLease?->unit_id ?? ($data['unit_id'] ?? null),
                'lease_id' => $activeLease?->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'priority' => $data['priority'] ?? 'medium',
                'status' => 'opened',
            ]);

            if ($activeLease) {
                $record->occupant_id = $activeLease->occupant_id;
            }

            $record->raised_by = $raiser->id;

            return $this->save($record);
        });
    }
}
