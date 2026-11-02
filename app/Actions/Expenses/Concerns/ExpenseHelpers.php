<?php

namespace App\Actions\Expenses\Concerns;

use App\Support\TenancyContext;

trait ExpenseHelpers
{
    private function tenantId(): ?string
    {
        return TenancyContext::tenantId();
    }
}
