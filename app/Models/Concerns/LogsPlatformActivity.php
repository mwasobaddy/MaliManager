<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Logs create/update/delete events for a platform-level (central, non-tenant)
 * model as a plain "action log" entry, in the same style as LogsTenantActivity.
 * The ip and user-agent are filled automatically by the audit creating listener.
 */
trait LogsPlatformActivity
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

        if (! empty($this->reference)) {
            return $this->reference;
        }

        if ($this->amount !== null) {
            return trim(class_basename($this).' '.number_format((float) $this->amount, 2));
        }

        return class_basename($this);
    }
}
