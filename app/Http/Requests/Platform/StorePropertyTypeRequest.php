<?php

namespace App\Http\Requests\Platform;

use App\Enums\PropertyCategory;
use App\Models\PropertyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Guards POST property-types.
 *
 * The slug is derived rather than accepted. It is the stable key for storage
 * paths and CSV imports, so letting a caller set it would let them point a
 * property at a folder that does not match its label.
 *
 * The catalog is platform-wide, so name and slug are unique across every
 * tenant rather than within one organization.
 *
 * Authorization is handled by the `can:createPropertyTypes` route middleware.
 */
class StorePropertyTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('property_types', 'name')->whereNull('deleted_at'),
            ],
            'category' => ['required', Rule::enum(PropertyCategory::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function passedValidation(): void
    {
        if ($this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->string('name')->toString())]);
        }
    }

    /**
     * Guard the derived slug.
     *
     * `Str::slug` can collide — "Bar / Pub" and "Bar-Pub" both become `bar-pub`
     * — and the database unique index would catch it as a 500. This reports it
     * against the field the user actually typed instead.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $slug = Str::slug($this->string('name')->toString());

            $exists = PropertyType::query()->where('slug', $slug)->exists();

            if ($exists) {
                $validator->errors()->add(
                    'name',
                    'Another type already uses this name once punctuation is removed.',
                );
            }
        });
    }
}
