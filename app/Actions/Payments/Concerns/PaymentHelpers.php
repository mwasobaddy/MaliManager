<?php

namespace App\Actions\Payments\Concerns;

use App\Support\TenancyContext;

trait PaymentHelpers
{
    private function tenantId(): ?string
    {
        return TenancyContext::tenantId();
    }
}
