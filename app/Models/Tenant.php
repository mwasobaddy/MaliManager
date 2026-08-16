<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * The tenancy registry row. Holds the tenant identity used by
 * stancl/tenancy (id is a UUID string). Business data lives on
 * the related Organization.
 *
 * @property string $id
 */
class Tenant extends BaseTenant
{
    use HasDomains, HasFactory;

    /** @use HasFactory<TenantFactory> */
    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    public function organization(): HasOne
    {
        return $this->hasOne(Organization::class);
    }
}
