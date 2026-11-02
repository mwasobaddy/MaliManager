<?php

declare(strict_types=1);

namespace App\Actions\Plans;

use App\Models\Plan;
use App\Services\Service;

/**
 * Removes a Plan. Authorization lives in the Form Request and the policy; this
 * owns the destructive call so no controller deletes directly.
 */
class DeletePlan extends Service
{
    public function handle(Plan $plan): void
    {
        $plan->delete();
    }
}
