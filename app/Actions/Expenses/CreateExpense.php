<?php

namespace App\Actions\Expenses;

use App\Actions\Expenses\Concerns\ExpenseHelpers;
use App\Models\Expense;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\Service;

class CreateExpense extends Service
{
    use ExpenseHelpers;

    public function handle(User $actor, int $organizationId, array $data): Expense
    {
        return $this->transaction(function () use ($actor, $organizationId, $data) {
            ['asset' => $asset, 'unitId' => $unitId] = app(ExpenseService::class)->resolveTarget($actor, $data + ['organization_id' => $organizationId]);

            $expense = new Expense([
                'tenant_id' => $this->tenantId(),
                'organization_id' => $organizationId,
                'category' => $data['category'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'KES',
                'spent_on' => $data['spent_on'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $expense->expenseable()->associate($asset);
            $expense->unit_id = $unitId;

            return $this->save($expense);
        });
    }
}
