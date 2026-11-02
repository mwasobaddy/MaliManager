<?php

declare(strict_types=1);

namespace App\Data\Audit;

final readonly class ExportAuditData
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public array $filters,
        public string $sortColumn,
        public string $sortDirection,
        public ?string $tenantId,
    ) {}
}
