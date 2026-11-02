<?php

declare(strict_types=1);

namespace App\Actions\Plans;

use App\Models\Plan;
use App\Services\Service;

/**
 * Applies validated changes to an existing Plan. Kept as an Action so the
 * controller stops persisting, matching the create path in the same domain.
 */
class UpdatePlan extends Service
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Plan $plan, array $data): Plan
    {
        $plan->update($data);

        return $plan;
    }
}
