<?php

declare(strict_types=1);

namespace App\Actions\PlatformExpenses;

use App\Models\PlatformExpense;
use App\Models\User;
use App\Services\Service;

/**
 * Creates a PlatformExpense from already-validated input. The Form Request owns the rules;
 * this owns the row so the controller stops persisting.
 */
class CreatePlatformExpense extends Service
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?User $actor): PlatformExpense
    {
        return PlatformExpense::create([...$data, 'created_by' => $actor?->id]);
    }
}
