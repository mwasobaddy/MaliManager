<?php

namespace App\Http\Requests\Tenant;

use App\Enums\PropertyCategory;
use App\Models\PropertyType;
use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Guards PATCH property-types/{propertyType}.
 *
 * Mirrors `StorePropertyTypeRequest`: the slug is re-derived on rename, so a
 * renamed type keeps a slug that matches its label. Slugs are only used as
 * lookup keys, never as storage paths, so re-deriving is safe — existing
 * properties point at the row id, not the slug.
 *
 * Authorization is handled by the `sub-permission:property_type.edit` route
 * middleware.
 */
class UpdatePropertyTypeRequest extends FormRequest
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
        $organizationId = TenancyContext::organization()?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // Ignore the row being edited, or saving without renaming would
                // collide with itself.
                Rule::unique('property_types', 'name')
                    ->where('organization_id', $organizationId)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('propertyType')?->getKey()),
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $organizationId = TenancyContext::organization()?->id;
            $slug = Str::slug($this->string('name')->toString());
            $id = $this->route('propertyType')?->getKey();

            $exists = PropertyType::query()
                ->where('organization_id', $organizationId)
                ->where('slug', $slug)
                ->when($id, fn ($query) => $query->whereKeyNot($id))
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'name',
                    'Another type already uses this name once punctuation is removed.',
                );
            }
        });
    }
}
