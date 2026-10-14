<?php

namespace App\Http\Requests\Platform;

use App\Enums\PlatformPermissionKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Guards PATCH platform/roles/{role}.
 *
 * Each submitted name must be a real `PlatformPermissionKey`. Validating
 * against the enum rather than against the `permissions` table is deliberate:
 * a stale row left behind by a removed enum case would otherwise still be
 * assignable, and the roles screen would render a checkbox for a permission
 * nothing checks.
 */
class UpdateRolePermissionsRequest extends FormRequest
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
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in($keys)],
        ];
    }
}
