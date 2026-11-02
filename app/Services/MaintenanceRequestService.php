<?php

namespace App\Services;

use App\Actions\Maintenance\Concerns\MaintenanceRequestHelpers;
use App\Actions\Maintenance\CreateMaintenanceRequest;
use App\Actions\Maintenance\DeleteMaintenanceRequest;
use App\Actions\Maintenance\UpdateMaintenanceRequest;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\User;

/**
 * The maintenance request workflow:
 *   opened → assigned → in_progress → resolved → closed
 *
 * Requests are raised by occupants with an active lease (or staff) and
 * handled by staff. All writes are atomic.
 */
class MaintenanceRequestService extends Service
{
    use MaintenanceRequestHelpers;

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
        return app(CreateMaintenanceRequest::class)->handle($raiser, $organizationId, $data);
    }

    /**
     * Update status/priority/assignment/resolution notes.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(MaintenanceRequest $request, User $actor, array $data): MaintenanceRequest
    {
        return app(UpdateMaintenanceRequest::class)->handle($request, $actor, $data);
    }

    public function softDelete(MaintenanceRequest $request): void
    {
        app(DeleteMaintenanceRequest::class)->handle($request);
    }
}
