<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Data\Roles\ExportRolesData;
use App\Models\Role;
use App\Services\Service;
use App\Support\CsvExport;

/**
 * The role CSV, honouring the index's filters.
 *
 * Permissions are pipe-separated, matching the import format, so a file can be
 * exported, edited by hand and imported back. Headers and column order are
 * unchanged.
 */
class ExportRoles extends Service
{
    public function handle(ExportRolesData $data): CsvExport
    {
        $query = Role::query()
            ->filtered([
                'search' => $data->search,
                'status' => $data->status,
                'type' => $data->type,
            ])
            ->with('permissions')
            ->orderBy('name');

        if ($data->scope === 'page') {
            $query->forPage($data->page, 15);
        }

        $rows = $query->get()->map(fn (Role $role): array => [
            $role->name,
            implode('|', $role->permissions->pluck('name')->all()),
            $role->is_active ? 'active' : 'inactive',
            $role->users()->count(),
            $role->isProtected() ? 'yes' : 'no',
        ]);

        return CsvExport::fromRows(
            'roles.csv',
            ['Name', 'Permissions', 'Status', 'Users', 'Protected'],
            $rows,
        );
    }
}
