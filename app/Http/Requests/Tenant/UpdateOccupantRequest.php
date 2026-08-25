<?php

namespace App\Http\Requests\Tenant;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOccupantRequest extends FormRequest
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
            'lease.agreement_document' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'lease.remove_agreement_document' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The rich-text editor produces HTML; keep only a safe formatting
     * allowlist so the stored agreement can be rendered without risk.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('lease.agreement_text')) {
            $this->merge([
                'lease' => array_merge($this->input('lease', []), [
                    'agreement_text' => strip_tags(
                        (string) $this->input('lease.agreement_text'),
                        '<p><br><b><strong><i><em><u><s><ul><ol><li><h1><h2><h3><blockquote><code>',
                    ),
                ]),
            ]);
        }
    }

    private function propertyId(?Property $property): ?int
    {
        return $property?->id;
    }
}
