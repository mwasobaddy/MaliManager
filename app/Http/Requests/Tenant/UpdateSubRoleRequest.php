<?php

namespace App\Http\Requests\Tenant;

use App\Enums\SubPermissionKey;
use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards PUT /roles/{subRole}. The landlord bundle is locked in the
 * action, not here: the form validates shape, the action owns the rule.
 */
class UpdateSubRoleRequest extends FormRequest
{
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
        $subRole = $this->route('subRole');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sub_roles', 'name')
                    ->where(fn ($query) => $query->where('organization_id', $organizationId))
                    ->ignore($subRole),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_column(SubPermissionKey::cases(), 'value'))],
        ];
    }
}
