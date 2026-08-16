<?php

namespace App\Enums;

/**
 * Platform-wide roles applied to user accounts (central, via spatie).
 * Organization-level access is handled by sub-roles, not these.
 */
enum PlatformRole: string
{
    case Admin = 'admin';
    case OrganizationOwner = 'organization-owner';
    case Tenant = 'tenant';
}
