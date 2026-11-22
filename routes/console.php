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

// Nightly platform and per-organization metrics rollup for trend charts.
Schedule::command('metrics:snapshot')->dailyAt('01:00');

// Rent ledger: flag newly-overdue leases at 1am, nudge their tenants at
// 3am. Africa/Nairobi wall-clock: the tenants are all Kenyan, and UTC
// midnight would fire texts in the small hours either way.
Schedule::command('rent:flag-overdue')->dailyAt('01:00')->timezone('Africa/Nairobi');
Schedule::command('rent:remind-overdue')->dailyAt('03:00')->timezone('Africa/Nairobi');

// Weekly portfolio digest (numbers + AI narrative) to owners and managers.
Schedule::command('ai:send-digest')->weeklyOn(1, '08:00');
