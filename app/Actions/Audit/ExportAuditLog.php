<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Data\Audit\ExportAuditData;
use App\Services\AuditService;
use App\Services\Service;
use App\Support\CsvExport;

/**
 * The audit-log CSV, for both the platform log and an organization's own.
 *
 * The tenant scope is carried by the DTO rather than read from tenancy, so the
 * same action serves both controllers and a test can exercise either. Headers
 * and column order match the previous `AuditService::toCsv()` exactly.
 */
class ExportAuditLog extends Service
{
    public function __construct(private AuditService $audit) {}

    public function handle(ExportAuditData $data): CsvExport
    {
        $audits = $this->audit
            ->query(
                $data->filters,
                $data->tenantId,
                $this->audit->sortFrom($data->sortColumn, $data->sortDirection),
            )
            ->get();

        $rows = $audits->map(function ($audit): array {
            $row = $this->audit->transform($audit);

            return [
                $row['created_at'],
                $row['causer']['name'] ?? ($row['causer']['email'] ?? 'System'),
                $row['event'] ?? '',
                $row['log_name'] ?? '',
                $row['subject'] ? $row['subject']['type'].' #'.$row['subject']['id'] : '',
                $row['description'] ?? '',
                $row['ip_address'] ?? '',
            ];
        });

        return CsvExport::fromRows(
            'audit-log.csv',
            ['Time', 'Actor', 'Event', 'Category', 'Subject', 'Description', 'IP address'],
            $rows,
        );
    }
}
