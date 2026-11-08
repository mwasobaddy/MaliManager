<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Saving a unit-browse filter for later re-runs.
 *
 * Self-service: the search belongs to the signed-in user and names no other
 * record. First save also creates the tenant identity (Person + Searcher role)
 * in the controller — a guest who saves a search is, by definition, searching.
 */
class StoreSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'filters' => ['required', 'array'],
            'filters.query' => ['nullable', 'string', 'max:255'],
            'filters.min_rent' => ['nullable', 'numeric', 'min:0'],
            'filters.max_rent' => ['nullable', 'numeric', 'min:0'],
            'filters.type' => ['nullable', 'string', 'max:64'],
        ];
    }
}
