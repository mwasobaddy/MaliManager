<?php

namespace App\Http\Requests\Tenant;

use App\Data\Ai\AiSettingsData;
use App\Enums\AiFeature;
use App\Enums\AiProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards PUT /ai-settings.
 *
 * The owner check stays in the controller: it reads the organization from the
 * tenancy context and aborts with a 403, which is the contract this screen
 * already has. This request only establishes a signed-in user.
 *
 * `api_key` is write-only — an empty value means "keep the existing key", which
 * is why it is nullable despite being required on the first save.
 */
class UpdateAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(array_column(AiProvider::cases(), 'value'))],
            'model' => ['required', 'string', 'max:100'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'min:20'],
            'features' => ['nullable', 'array'],
            'features.*' => [Rule::in(array_column(AiFeature::cases(), 'value'))],
            'allow_all_members' => ['nullable', 'boolean'],
            'allowed_user_ids' => ['nullable', 'array'],
            'allowed_user_ids.*' => ['integer'],
        ];
    }

    public function toData(): AiSettingsData
    {
        $validated = $this->validated();

        return new AiSettingsData(
            provider: (string) $validated['provider'],
            model: (string) $validated['model'],
            baseUrl: $validated['base_url'] ?? null,
            features: array_values($validated['features'] ?? []),
            apiKey: ! empty($validated['api_key']) ? (string) $validated['api_key'] : null,
            allowAllMembers: $this->boolean('allow_all_members'),
            allowedUserIds: $validated['allowed_user_ids'] ?? [],
        );
    }
}
