<?php

declare(strict_types=1);

namespace App\Data\PlatformExpenses;

final readonly class ExportPlatformExpensesData
{
    public function __construct(
        public string $scope,
        public int $page,
        public string $search,
        public string $category,
        public string $from,
        public string $to,
    ) {}
}
