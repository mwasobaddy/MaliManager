<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Public unit browsing filters.
 *
 * Read-only and public: the list only ever returns the public scope, so there
 * is no record to authorize against. Validation exists so a malformed rent
 * bound is reported rather than silently ignored.
 */
class BrowseListingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public list; the scope itself is the access control.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:255'],
            'min_rent' => ['nullable', 'numeric', 'min:0'],
            'max_rent' => ['nullable', 'numeric', 'min:0'],
            'type' => ['nullable', 'string', 'max:64'],
        ];
    }
}
