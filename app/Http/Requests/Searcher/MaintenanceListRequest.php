<?php

namespace App\Http\Requests\Searcher;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The occupant's own maintenance list.
 *
 * Read-only and parameter-free: rows are scoped to the caller's user id, so
 * there is nothing to validate and no other record to authorize.
 */
class MaintenanceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Own rows only; the query filters by the authenticated user.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
