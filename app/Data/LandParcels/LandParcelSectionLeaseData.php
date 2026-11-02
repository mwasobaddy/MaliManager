<?php

declare(strict_types=1);

namespace App\Data\LandParcels;

/**
 * Input for leasing a land parcel section. Kept as a typed object so the action
 * never reaches into the request, and so the same lease can be created from a
 * console command or a job without inventing a fake Request.
 */
final readonly class LandParcelSectionLeaseData
{
    public function __construct(
        public ?int $personId,
        public string $tenantName,
        public string $tenantEmail,
        public float|int|string $rentAmount,
        public ?string $rentFrequency,
        public ?string $currency,
        public float|int|string|null $deposit,
        public string $startsAt,
        public ?string $endsAt,
        public ?int $templateId,
        public ?string $tenantId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated, ?string $tenantId = null): self
    {
        return new self(
            personId: isset($validated['person_id']) ? (int) $validated['person_id'] : null,
            tenantName: (string) ($validated['tenant_name'] ?? ''),
            tenantEmail: (string) ($validated['tenant_email'] ?? ''),
            rentAmount: $validated['rent_amount'],
            rentFrequency: $validated['rent_frequency'] ?? null,
            currency: $validated['currency'] ?? null,
            deposit: $validated['deposit'] ?? null,
            startsAt: (string) $validated['starts_at'],
            endsAt: $validated['ends_at'] ?? null,
            templateId: isset($validated['template_id']) ? (int) $validated['template_id'] : null,
            tenantId: $tenantId,
        );
    }
}
