<?php

namespace App\Http\Requests\Settings;

use App\Data\Ai\AiSettingsData;
use App\Enums\AiFeature;
use App\Enums\AiProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards PUT /settings/personal-ai.
 *
 * A personal credential has no member allow-list, so the rules are the subset
 * the personal screen actually renders. `api_key` is write-only: an empty value
 * means "keep the existing key", and the controller rejects a first save that
 * has none.
 */
class UpdatePersonalAiSettingsRequest extends FormRequest
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
        );
    }
}
