<?php

namespace App\Http\Requests\Platform;

use App\Enums\PlatformPermissionKey;
use App\Enums\PlatformRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Guards POST platform/roles.
 *
 * Creating a role is what makes roles dynamic rather than four enum cases, so
 * two things are worth pinning down:
 *
 * - `name` may not collide with a built-in. The `roles.name` unique index
 *   would catch it anyway, but as a database error rather than a field error,
 *   and the message would not say which role is protected.
 * - `permissions` must be real `PlatformPermissionKey` values, for the same
 *   reason `UpdateRolePermissionsRequest` checks the enum: a stale row left by
 *   a removed case would otherwise be assignable and offered as a checkbox
 *   that nothing checks.
 *
 * Authorization is handled by the `can:createRoles` route middleware.
 */
class StoreRoleRequest extends FormRequest
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
        $keys = Collection::make(PlatformPermissionKey::cases())->map->value->all();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // Lowercase-and-dashed, matching the built-in names, so a role
                // called "Billing Manager" and one called "billing-manager"
                // cannot both exist and look identical in a dropdown.
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(array_column(PlatformRole::cases(), 'value')),
                Rule::unique('roles', 'name'),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in($keys)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Use lowercase words separated by hyphens, e.g. billing-manager.',
            'name.not_in' => 'That name belongs to a built-in role and cannot be reused.',
        ];
    }
}
