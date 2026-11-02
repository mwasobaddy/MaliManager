<?php

namespace App\Http\Requests\Tenant;

use App\Models\AiConversation;
use App\Support\TenancyContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Viewing or deleting one organization-assistant conversation.
 *
 * Ownership and membership are both asserted here: the conversation must belong
 * to the caller and to the organization currently in context. A platform-scope
 * conversation has no organization id, so it cannot be reached through a tenant
 * route even by the user who owns it.
 */
class AssistantConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organizationId = TenancyContext::organization()?->id;

        if ($organizationId === null) {
            return false;
        }

        return AiConversation::query()
            ->whereKey($this->route('id'))
            ->where('user_id', $this->user()?->id)
            ->where('organization_id', $organizationId)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
