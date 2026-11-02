<?php

declare(strict_types=1);

namespace App\Data\PropertyTypes;

final readonly class ExportPropertyTypesData
{
    public function __construct(
        public string $scope,
        public int $page,
        public string $search,
        public string $category,
    ) {}
}
