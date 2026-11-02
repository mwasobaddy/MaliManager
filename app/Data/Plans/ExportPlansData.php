<?php

declare(strict_types=1);

namespace App\Data\Plans;

final readonly class ExportPlansData
{
    public function __construct(
        public string $scope,
        public int $page,
        public string $search,
        public string $status,
    ) {}
}
