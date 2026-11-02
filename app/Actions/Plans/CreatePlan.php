<?php

declare(strict_types=1);

namespace App\Actions\Plans;

use App\Models\Plan;
use App\Models\User;
use App\Services\Service;

/**
 * Creates a Plan from already-validated input. The Form Request owns the rules;
 * this owns the row so the controller stops persisting.
 */
class CreatePlan extends Service
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?User $actor): Plan
    {
        return Plan::create([...$data, 'created_by' => $actor?->id]);
    }
}
