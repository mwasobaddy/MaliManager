<?php

namespace App\Http\Requests\Tenant;

use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLandParcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = TenancyContext::organization()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'title_deed_number' => ['nullable', 'string', 'max:255'],
            'acreage' => ['nullable', 'numeric', 'min:0'],
            'zoning' => ['required', 'string', 'in:residential,commercial,agricultural,mixed,industrial'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'available_for_lease' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'manager_ids' => ['nullable', 'array'],
            'manager_ids.*' => ['integer', 'exists:users,id'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'max:10240'],
            'remove_media_ids' => ['nullable', 'array'],
            'remove_media_ids.*' => ['integer'],
        ];
    }
}
