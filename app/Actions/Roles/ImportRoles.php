<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Data\Roles\ImportRolesData;
use App\Enums\PlatformPermissionKey;
use App\Enums\PlatformRole;
use App\Models\Role;
use App\Services\RoleManagementService;
use App\Services\Service;
use App\Support\CsvReader;
use App\Support\ImportResult;

/**
 * Create custom roles from an uploaded CSV.
 *
 * Rows are independent: a bad row is recorded and skipped while the good rows
 * still land. A row naming a built-in role is rejected rather than skipped,
 * because an operator's intent and the file disagree there and importing it as
 * a success would do less than asked. Writes go through `RoleManagementService`
 * so the role and its permissions are created the same way as from the UI.
 */
class ImportRoles extends Service
{
    public function __construct(
        private CsvReader $csv,
        private RoleManagementService $roles,
    ) {}

    public function handle(ImportRolesData $data): ImportResult
    {
        return $this->transaction(function () use ($data): ImportResult {
            $parsed = $this->csv->rows($data->file);
            $errors = [];
            $imported = 0;

            foreach ($parsed['rows'] as $row) {
                $rowData = $row['data'];
                $name = strtolower(trim($rowData['name'] ?? ''));

                if ($name === '') {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: Name is required."];

                    continue;
                }

                if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name)) {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: \"{$name}\" must be lowercase words separated by hyphens."];

                    continue;
                }

                if (PlatformRole::tryFrom($name) !== null) {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: \"{$name}\" is a built-in role and cannot be imported."];

                    continue;
                }

                if (Role::where('name', $name)->exists()) {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: the role \"{$name}\" already exists."];

                    continue;
                }

                $names = collect(explode('|', $rowData['permissions'] ?? ''))
                    ->map(fn (string $permission): string => trim($permission))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $unknown = array_diff($names, array_column(PlatformPermissionKey::cases(), 'value'));

                if ($unknown !== []) {
                    $errors[] = ['row' => $row['row'], 'message' => "Row {$row['row']}: unknown permission(s) ".implode(', ', $unknown).'.'];

                    continue;
                }

                $status = strtolower(trim($rowData['status'] ?? 'active'));
                $isActive = ! in_array($status, ['inactive', 'disabled', '0', 'no'], true);

                $this->roles->create($name, $names, $isActive);

                $imported++;
            }

            if (! $parsed['ok']) {
                $errors = $parsed['rows'];
            }

            return new ImportResult($imported, count($errors), $errors);
        });
    }
}
