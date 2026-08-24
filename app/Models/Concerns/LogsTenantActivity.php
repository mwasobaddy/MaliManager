<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

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
                'created' => "Created {$this->auditName()}",
                'updated' => "Updated {$this->auditName()}",
                'deleted' => "Deleted {$this->auditName()}",
                'restored' => "Restored {$this->auditName()}",
                default => "{$event} {$this->auditName()}",
            });
    }

    protected function auditName(): string
    {
        if (! empty($this->name)) {
            return $this->name;
        }

        if (! empty($this->first_name) || ! empty($this->last_name)) {
            return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
        }

        return class_basename($this);
    }
}
