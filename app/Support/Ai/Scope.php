<?php

namespace App\Support\Ai;

use App\Models\Organization;

/**
 * The data scope the assistant is allowed to read. Exactly one of the
 * mutually-exclusive modes is active:
 *  - platform: central/admin context, no forced tenant filter (may still
 *    filter by `organization` via the query tool).
 *  - org: a single organization's data.
 *  - person: a single occupant's own leases + maintenance requests.
 *
 * The compiler never trusts the client for scope — it is injected
 * server-side from the authenticated caller.
 */
final class Scope
{
    public const string PLATFORM = 'platform';

    public const string ORG = 'org';

    public const string PERSON = 'person';

    private function __construct(
        public readonly string $type,
        public readonly ?int $organizationId = null,
        public readonly ?int $personId = null,
        public readonly ?int $userId = null,
    ) {}

    public static function platform(): self
    {
        return new self(self::PLATFORM);
    }

    public static function org(int $organizationId): self
    {
        return new self(self::ORG, organizationId: $organizationId);
    }

    public static function person(int $personId, int $userId): self
    {
        return new self(self::PERSON, personId: $personId, userId: $userId);
    }

    public function organization(): ?Organization
    {
        return $this->organizationId ? Organization::find($this->organizationId) : null;
    }
}
