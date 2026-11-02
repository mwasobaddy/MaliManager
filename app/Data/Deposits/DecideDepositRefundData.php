<?php

declare(strict_types=1);

namespace App\Data\Deposits;

final readonly class DecideDepositRefundData
{
    public function __construct(
        public bool $approve,
        public ?string $note = null,
    ) {}
}
