<?php

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\InspectionHelpers;
use App\Models\Inspection;
use App\Services\Service;
use App\Support\Ai\AiGateway;

class DeleteInspection extends Service
{
    use InspectionHelpers;

    public function __construct(private AiGateway $gateway) {}

    public function handle(Inspection $inspection): void
    {
        $this->transaction(function () use ($inspection): void {
            $this->delete($inspection);
        });
    }
}
