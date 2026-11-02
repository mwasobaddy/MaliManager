<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Data\Users\ExportUsersData;
use App\Models\User;
use App\Services\Service;
use App\Support\CsvExport;

/**
 * The user CSV, honouring the index's search, status and role filters.
 *
 * Roles are pipe-separated, matching the import format. Headers and column
 * order are unchanged.
 */
class ExportUsers extends Service
{
    public function handle(ExportUsersData $data): CsvExport
    {
        $query = User::query()
            ->filtered([
                'search' => $data->search,
                'status' => $data->status,
                'role' => $data->role,
            ])
            ->with('person:id,first_name,last_name,email,phone,national_id,gender,status')
            ->with('roles:id,name')
            ->orderBy('id');

        if ($data->scope === 'page') {
            $query->forPage($data->page, 15);
        }

        $rows = $query->get()->map(fn (User $user): array => [
            $user->id,
            $user->person?->first_name ?? '',
            $user->person?->last_name ?? '',
            $user->email,
            $user->phone ?? '',
            $user->status,
            $user->roles->pluck('name')->implode('|'),
            $user->created_at?->toDateTimeString() ?? '',
        ]);

        $suffix = $data->scope === 'page' ? '-page'.$data->page : '-all';

        return CsvExport::fromRows(
            'users'.$suffix.'.csv',
            ['ID', 'First name', 'Last name', 'Email', 'Phone', 'Status', 'Roles', 'Created at'],
            $rows,
        );
    }
}
