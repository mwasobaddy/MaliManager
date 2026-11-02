<?php

declare(strict_types=1);

namespace App\Data\Roles;

final readonly class ExportRolesData
{
    public function __construct(
        public string $scope,
        public int $page,
        public string $search,
        public string $status,
        public string $type,
    ) {}
}
