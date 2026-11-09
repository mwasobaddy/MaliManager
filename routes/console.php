<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The activity log grows unbounded otherwise; audit pages filter on it.
Schedule::command('activity:prune --days=180')->daily();

// Trial-ending and lapsed billing reminders on each owner's channel.
Schedule::command('billing:remind')->dailyAt('09:00');

// Weekly portfolio digest (numbers + AI narrative) to owners and managers.
Schedule::command('ai:send-digest')->weeklyOn(1, '08:00');
