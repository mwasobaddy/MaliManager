<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Tenant-scoped audit record. Extends the spatie Activity model and adds
 * the columns we use for an organization "action log": a tenant id for
 * scoping, plus the request ip/user-agent for security context.
 */
class Audit extends Activity
{
    protected $fillable = [
        'tenant_id',
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'event',
        'causer_type',
        'causer_id',
        'attribute_changes',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'attribute_changes' => 'array',
            'properties' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Scope activities to a single organization's tenancy. A null tenant
     * id is a no-op so admin views can list every organization's records.
     */
    public function scopeForTenant(Builder $query, ?string $tenantId): Builder
    {
        if ($tenantId === null) {
            return $query;
        }

        return $query->where('tenant_id', $tenantId);
    }
}
