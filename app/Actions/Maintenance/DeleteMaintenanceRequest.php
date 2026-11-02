<?php

namespace App\Actions\Maintenance;

use App\Actions\Maintenance\Concerns\MaintenanceRequestHelpers;
use App\Models\MaintenanceRequest;
use App\Services\Service;

class DeleteMaintenanceRequest extends Service
{
    use MaintenanceRequestHelpers;

    public function handle(MaintenanceRequest $request): void
    {
        $this->transaction(function () use ($request) {
            $this->delete($request);
        });
    }
}
