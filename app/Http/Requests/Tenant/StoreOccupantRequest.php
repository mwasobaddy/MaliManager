<?php

namespace App\Http\Requests\Tenant;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOccupantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $property = $this->route('property');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'moved_out'])],
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => ['integer', Rule::exists('units', 'id')->where(fn ($query) => $query->where('property_id', $this->propertyId($property)))],
            'lease' => ['nullable', 'array'],
            'lease.starts_at' => ['nullable', 'date'],
            'lease.rent_amount' => ['nullable', 'numeric', 'min:0'],
            'lease.rent_frequency' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'lease.deposit' => ['nullable', 'numeric', 'min:0'],
            'lease.currency' => ['nullable', 'string', 'size:3'],
            'lease.agreement_text' => ['nullable', 'string'],
        ];
    }

    private function propertyId(?Property $property): ?int
    {
        return $property?->id;
    }
}
