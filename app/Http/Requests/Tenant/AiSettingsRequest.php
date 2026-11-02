<?php

namespace App\Http\Requests\Tenant;

use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The organization AI settings screen and its key removal.
 *
 * Both are owner-only: the key is paid for by the owner, and the screen lists
 * every member's token usage. The check needs the organization from the tenancy
 * context rather than a route model, so it lives here instead of a policy.
 */
class AiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = TenancyContext::organization();

        return $organization !== null
            && ($this->user()?->isOwnerOf($organization) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
