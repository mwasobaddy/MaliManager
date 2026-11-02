<?php

declare(strict_types=1);

namespace App\Data\Ai;

/**
 * Supported attributes for an AI setting. `allowAllMembers` and `allowedUserIds`
 * are org-scope only: null means "not applicable to this scope" and the action
 * leaves those columns untouched.
 */
final readonly class AiSettingsData
{
    /**
     * @param  array<int, string>  $features
     * @param  array<int, int>|null  $allowedUserIds
     */
    public function __construct(
        public string $provider,
        public string $model,
        public ?string $baseUrl,
        public array $features,
        public ?string $apiKey,
        public ?bool $allowAllMembers = null,
        public ?array $allowedUserIds = null,
    ) {}
}
