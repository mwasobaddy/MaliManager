<?php

namespace App\Http\Requests\Searcher;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The searcher's own rental history.
 *
 * Read-only and parameter-free: rows resolve from the caller's linked person,
 * so there is nothing to validate and no other record to authorize.
 */
class LeaseListRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Own history only; the query resolves the caller's person.
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
