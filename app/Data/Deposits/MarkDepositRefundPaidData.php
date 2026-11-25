<?php

declare(strict_types=1);

namespace App\Data\Deposits;

/**
 * Boundary object between the pay Form Request and the Action.
 */
final readonly class MarkDepositRefundPaidData
{
    public function __construct(
        public ?string $reference,
    ) {}
}
