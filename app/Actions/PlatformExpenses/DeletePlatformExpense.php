<?php

declare(strict_types=1);

namespace App\Actions\PlatformExpenses;

use App\Models\PlatformExpense;
use App\Services\Service;

/**
 * Removes a PlatformExpense. Authorization lives in the Form Request and the policy; this
 * owns the destructive call so no controller deletes directly.
 */
class DeletePlatformExpense extends Service
{
    public function handle(PlatformExpense $platformExpense): void
    {
        $platformExpense->delete();
    }
}
