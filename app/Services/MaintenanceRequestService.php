<?php

namespace App\Services;

use App\Enums\PlatformRole;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Support\TenancyContext;

/**
 * The maintenance request workflow:
 *   opened → assigned → in_progress → resolved → closed
 *
 * Requests are raised by occupants with an active lease (or staff) and
 * handled by staff. All writes are atomic.
 */
class MaintenanceRequestService extends Service
{
    /**
     * Valid status transitions.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'opened' => ['assigned', 'in_progress', 'closed'],
        'assigned' => ['in_progress', 'resolved', 'closed'],
        'in_progress' => ['resolved', 'closed'],
        'resolved' => ['closed'],
        'closed' => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * The statuses the given status can move to.
     *
     * @return list<string>
     */
    public function transitionsFrom(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    /**
     * Raise a request. Occupants must hold an active lease on the target
     * unit/property; staff bypass this check (gated by sub-permissions).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $raiser, int $organizationId, array $data): MaintenanceRequest
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

    /**
     * Update status/priority/assignment/resolution notes.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(MaintenanceRequest $request, User $actor, array $data): MaintenanceRequest
    {
        return $this->transaction(function () use ($request, $data) {
            if (isset($data['status']) && $data['status'] !== $request->status) {
                abort_unless(
                    $this->canTransition($request->status, $data['status']),
                    422,
                    "Cannot move a {$request->status} request to {$data['status']}.",
                );

                $request->status = $data['status'];

                if ($data['status'] === 'resolved') {
                    $request->resolved_at = now();
                    $request->resolution_notes = $data['resolution_notes'] ?? null;
                }
            }

            if (array_key_exists('priority', $data)) {
                $request->priority = $data['priority'];
            }

            if (array_key_exists('assigned_to', $data)) {
                $request->assigned_to = $data['assigned_to'] ?: null;

                if ($request->assigned_to !== null && $request->status === 'opened') {
                    $request->status = 'assigned';
                }
            }

            return $this->save($request);
        });
    }

    public function softDelete(MaintenanceRequest $request): void
    {
        $this->transaction(function () use ($request) {
            $this->delete($request);
        });
    }

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
