<?php

namespace App\Http\Requests\Platform;

use App\Data\Roles\ImportRolesData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guards POST platform/roles/import.
 *
 * Authorization is handled by the `can:importRoles` route middleware.
 */
class ImportRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('importRoles') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    public function toData(): ImportRolesData
    {
        return new ImportRolesData(file: $this->file('file'));
    }
}
