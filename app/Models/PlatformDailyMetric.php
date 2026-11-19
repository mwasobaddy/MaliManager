<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One platform rollup row per day, written by metrics:snapshot.
 *
 * @property int $id
 * @property CarbonImmutable $captured_on
 * @property string $mrr
 * @property int $paying_organizations
 * @property int $active_organizations
 * @property int $trials
 * @property int $past_due
 * @property int $signups_month_to_date
 * @property string $income_month_to_date
 * @property int $properties_total
 * @property int $units_total
 * @property string $rent_month_to_date
 * @property int $maintenance_open
 */
class PlatformDailyMetric extends Model
{
    protected $fillable = [
        'captured_on',
        'mrr',
        'paying_organizations',
        'active_organizations',
        'trials',
        'past_due',
        'signups_month_to_date',
        'income_month_to_date',
        'properties_total',
        'units_total',
        'rent_month_to_date',
        'maintenance_open',
    ];

    protected function casts(): array
    {
        return [
            'captured_on' => 'date',
            'mrr' => 'decimal:2',
            'income_month_to_date' => 'decimal:2',
            'rent_month_to_date' => 'decimal:2',
        ];
    }
}
