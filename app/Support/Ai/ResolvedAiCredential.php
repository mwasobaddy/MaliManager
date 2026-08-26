<?php

namespace App\Support\Ai;

use App\Enums\AiProvider;
use App\Models\AiSetting;
use Prism\Prism\Enums\Provider as PrismProvider;

/**
 * A resolved BYO credential ready for a Prism call. Never serializable to
 * responses — the API key stays server-side.
 *
 * - driver: the Prism provider enum value to pass to using()
 * - requestConfig: api_key + optional url override for usingProviderConfig()
 */
class ResolvedAiCredential
{
    public function __construct(
        public readonly AiSetting $setting,
        public readonly string $provider,
        public readonly string $model,
        public readonly string $apiKey,
        public readonly string $source,
    ) {}

    /**
     * The Prism driver enum value (NVIDIA routes through the OpenAI driver).
     */
    public function driver(): string
    {
        return AiProvider::from($this->setting->provider)->prismDriver();
    }

    /**
     * Provider configuration for the request: key plus endpoint override.
     *
     * @return array<string, string>
     */
    public function requestConfig(): array
    {
        $config = ['api_key' => $this->apiKey];

        $url = $this->setting->base_url
            ?: AiProvider::tryFrom($this->setting->provider)?->baseUrl();

        if ($url !== null) {
            $config['url'] = rtrim($url, '/');
        }

        return $config;
    }

    /**
     * Convenience: the Prism Provider enum backing the credential's driver.
     */
    public function prismProvider(): PrismProvider
    {
        return PrismProvider::from($this->driver());
    }
}
