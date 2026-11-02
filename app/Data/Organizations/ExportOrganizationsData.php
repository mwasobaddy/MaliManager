<?php

declare(strict_types=1);

namespace App\Data\Organizations;

final readonly class ExportOrganizationsData
{
    public function __construct(
        public string $scope,
        public int $page,
        public string $search,
        public string $status,
    ) {}
}
