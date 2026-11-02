<?php

namespace App\Actions\Maintenance;

use App\Actions\Maintenance\Concerns\MaintenanceRequestHelpers;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\MaintenanceRequestService;
use App\Services\Service;

class UpdateMaintenanceRequest extends Service
{
    use MaintenanceRequestHelpers;

    public function handle(MaintenanceRequest $request, User $actor, array $data): MaintenanceRequest
    {
        return $this->transaction(function () use ($request, $data) {
            if (isset($data['status']) && $data['status'] !== $request->status) {
                abort_unless(
                    app(MaintenanceRequestService::class)->canTransition($request->status, $data['status']),
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
}
