<?php

declare(strict_types=1);

namespace App\Data\Deposits;

final readonly class RequestDepositRefundData
{
    public function __construct(
        public int $organizationId,
        public int $leaseId,
        public int $amountCents,
        public string $reason,
        public string $currency = 'KES',
    ) {}
}
