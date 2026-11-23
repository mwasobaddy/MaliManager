<?php

declare(strict_types=1);

namespace App\Data\Leases;

final readonly class ExportLeasesData
{
    public function __construct(
        public string $scope,
        public int $page,
        public string $search,
        public string $status,
        public bool $expiring,
        public bool $overdue,
        public string $sort,
        public string $direction,
        public array $columns, ) {}
}
