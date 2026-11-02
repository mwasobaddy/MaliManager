<?php

namespace App\Actions\Expenses;

use App\Actions\Expenses\Concerns\ExpenseHelpers;
use App\Models\Expense;
use App\Services\Service;

class DeleteExpense extends Service
{
    use ExpenseHelpers;

    public function handle(Expense $expense): void
    {
        $this->transaction(function () use ($expense) {
            $this->delete($expense);
        });
    }
}
