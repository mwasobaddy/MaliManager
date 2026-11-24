<?php

declare(strict_types=1);

namespace App\Data\Metering;

final readonly class ExportUtilityReadingsData
{
    public function __construct(
        public string $scope,
        public int $page,
        public ?int $unitId,
        public string $utility,
        public string $search,
        public string $sort,
        public string $direction,
        public array $columns, ) {}
}
