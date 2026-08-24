<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Logs create/update/delete events for a tenant-scoped model as a plain
 * "action log" entry (no attribute diffs). The tenant id, ip and
 * user-agent are filled automatically by the audit creating listener.
 */
trait LogsTenantActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([])
            ->setDescriptionForEvent(fn (string $event): string => match ($event) {
                'created' => 'created',
                'updated' => 'updated',
                'deleted' => 'deleted',
                'restored' => 'restored',
                default => $event,
            });
    }
}
