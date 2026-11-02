<?php

namespace App\Http\Requests\Tenant;

use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Leasing a land-parcel section.
 *
 * The rules were previously assembled inline with `Validator::validate()`, so
 * they could not be reused or read without the action. A section can either
 * reference an existing person or name a new one, which is why the tenant fields
 * are `requiredIf` rather than always required.
 */
class LeaseLandParcelSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = TenancyContext::organization();
        $user = $this->user();

        if ($organization === null || $user === null) {
            return false;
        }

        return $user->isOwnerOf($organization)
            || $user->membershipFor($organization) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'person_id' => ['nullable', Rule::exists('people', 'id')],
            'tenant_name' => [Rule::requiredIf(blank($this->input('person_id'))), 'string', 'max:255'],
            'tenant_email' => [Rule::requiredIf(blank($this->input('person_id'))), 'email', 'max:255'],
            'template_id' => ['nullable', Rule::exists('agreement_templates', 'id')],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'rent_frequency' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'max:10'],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
