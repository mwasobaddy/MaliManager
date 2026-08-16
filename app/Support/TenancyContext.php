<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\Tenant;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;

/**
 * Convenience accessors for the currently initialized tenancy context.
 * In single-database mode the tenant registry row identifies the tenant;
 * the Organization is the business profile linked to it.
 */
class TenancyContext
{
    public static function initialized(): bool
    {
        return tenancy()->initialized;
    }

    public static function tenant(): ?Tenant
    {
        return self::initialized() ? tenant() : null;
    }

    public static function tenantId(): ?string
    {
        return self::tenant()?->getTenantKey();
    }

    public static function organization(): ?Organization
    {
        return self::tenant()?->organization;
    }

    public static function domain(): ?Domain
    {
        return DomainTenantResolver::$currentDomain;
    }
}
