<?php

declare(strict_types=1);

namespace App\Actions\PlatformExpenses;

use App\Models\PlatformExpense;
use App\Services\Service;

/**
 * Applies validated changes to an existing PlatformExpense. Kept as an Action so the
 * controller stops persisting, matching the create path in the same domain.
 */
class UpdatePlatformExpense extends Service
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PlatformExpense $platformExpense, array $data): PlatformExpense
    {
        $platformExpense->update($data);

        return $platformExpense;
    }
}
