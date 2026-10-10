<?php

namespace App\Enums;

/**
 * Lifecycle state of an Organization.
 *
 * Previously a bare string column with no constraint; the only guard was a
 * Rule::in() on the edit form. Suspension is enforced by the
 * `organization.active` middleware (EnsureOrganizationIsActive), which blocks
 * tenant requests for a suspended organization.
 */
enum OrganizationStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }
}
