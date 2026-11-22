<?php

declare(strict_types=1);

namespace App\Data\Units;

final readonly class ExportUnitsData
{
    public function __construct(
        public string $scope,
        public int $page,
        public string $search,
        public string $status,
        public string $property,
        public string $sort,
        public string $direction,
        public array $columns, ) {}
}
