<?php

namespace App\Http\Requests\Tenant;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            // A pasted map link, validated as http(s) rather than just "a
            // string": the column is rendered as a link, and javascript: or a
            // stray note in that field would become a clickable href.
            'location_url' => ['nullable', 'url:http,https', 'max:2048'],
            'property_type_id' => [
                'nullable',
                'integer',
                // The catalog is platform-wide, so the scope is existence plus
                // "not retired" rather than an organization match.
                Rule::exists('property_types', 'id')->whereNull('deleted_at'),
            ],
            'units' => ['nullable', 'array', 'max:100'],
            'units.*.name' => ['required', 'string', 'max:255'],
            'units.*.type' => ['nullable', 'string', 'max:255'],
            'units.*.monthly_rent' => ['nullable', 'numeric', 'min:0'],
            // Nested per-unit photos: the form posts one file input per unit
            // draft, named `units[0][photos][]` and so on.
            'units.*.photos' => ['nullable', 'array', 'max:'.Unit::MAX_PHOTOS],
            'units.*.photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];
    }
}
