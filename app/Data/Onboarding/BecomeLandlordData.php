<?php

declare(strict_types=1);

namespace App\Data\Onboarding;

final readonly class BecomeLandlordData
{
    public function __construct(
        public string $organizationName,
        public string $currency,
        public bool $listPublicly = false,
    ) {}
}
