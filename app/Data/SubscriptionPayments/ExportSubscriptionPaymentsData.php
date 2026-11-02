<?php

declare(strict_types=1);

namespace App\Data\SubscriptionPayments;

final readonly class ExportSubscriptionPaymentsData
{
    public function __construct(
        public string $scope,
        public int $page,
        public string $search,
        public string $from,
        public string $to,
    ) {}
}
