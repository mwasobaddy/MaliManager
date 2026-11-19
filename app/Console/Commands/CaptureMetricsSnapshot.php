<?php

namespace App\Console\Commands;

use App\Actions\Metrics\CapturePlatformSnapshot;
use Illuminate\Console\Command;

class CaptureMetricsSnapshot extends Command
{
    protected $signature = 'metrics:snapshot {--organization= : Limit to one organization id}';

    protected $description = 'Capture the daily platform and per-organization metrics snapshot';

    public function handle(CapturePlatformSnapshot $capture): int
    {
        $option = $this->option('organization');
        $summary = $capture->handle($option !== null ? (int) $option : null);

        $this->info("Snapshot for {$summary['date']}: {$summary['organizations']} organizations, {$summary['skipped']} skipped.");

        return self::SUCCESS;
    }
}
