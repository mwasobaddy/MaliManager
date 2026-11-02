<?php

namespace App\Actions\Expenses;

use App\Actions\Expenses\Concerns\ExpenseHelpers;
use App\Models\Expense;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\Service;

class UpdateExpense extends Service
{
    use ExpenseHelpers;

    public function handle(Expense $expense, User $actor, array $data): Expense
    {
        return $this->transaction(function () use ($expense, $actor, $data) {
            $resolved = app(ExpenseService::class)->resolveTarget($actor, $data + ['organization_id' => $expense->organization_id]);

            $expense->fill([
                'category' => $data['category'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? $expense->currency,
                'spent_on' => $data['spent_on'],
                'notes' => $data['notes'] ?? null,
                'unit_id' => $resolved['unitId'],
            ]);
            $expense->expenseable()->associate($resolved['asset']);

            return $this->save($expense);
        });
    }
}
