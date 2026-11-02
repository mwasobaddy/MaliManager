<?php

declare(strict_types=1);

namespace App\Data\Users;

final readonly class ExportUsersData
{
    public function __construct(
        public string $scope,
        public int $page,
        public string $search,
        public string $status,
        public string $role,
    ) {}
}
