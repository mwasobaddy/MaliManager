<?php

declare(strict_types=1);

namespace App\Actions\Plans;

use App\Models\Plan;
use App\Services\Service;

/**
 * Activates or deactivates a plan. `is_active` gates which tiers a new
 * organization can be placed on, so the transition lives here rather than in a
 * controller action.
 */
class SetPlanActive extends Service
{
    public function handle(Plan $plan, bool $active): Plan
    {
        $plan->update(['is_active' => $active]);

        return $plan;
    }
}
