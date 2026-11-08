<?php

namespace App\Http\Requests;

use App\Models\SavedSearch;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting one saved search.
 *
 * Ownership is the whole rule: a search belongs to exactly one user, so the
 * request asserts the bound record is theirs. No policy needed — there is
 * nothing to share or delegate.
 */
class DestroySavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $search = $this->route('search');

        return $search instanceof SavedSearch
            && $search->user_id === $this->user()?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
