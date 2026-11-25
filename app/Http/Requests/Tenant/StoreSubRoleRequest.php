<?php

namespace App\Http\Requests\Tenant;

use App\Enums\SubPermissionKey;
use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards POST /staff/roles. Route middleware (sub-permission:staff.manage)
 * is the gate; this form only validates shape.
 */
class StoreSubRoleRequest extends FormRequest
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

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sub_roles', 'name')->where(fn ($query) => $query->where('organization_id', $organizationId)),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            // Nothing ticked posts nothing at all, which syncs to an empty
            // set — a valid view-only role — so the key stays nullable.
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_column(SubPermissionKey::cases(), 'value'))],
        ];
    }
}
