<?php

declare(strict_types=1);

namespace App\Data\Deposits;

/**
 * Boundary object for the deductions-review decision.
 */
final readonly class ReviewDepositDeductionsData
{
    public function __construct(
        public bool $approve,
        public ?string $note,
    ) {}
}
