<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cumulative-counters row per organization per day, written by
 * metrics:snapshot.
 *
 * @property int $id
 * @property int $organization_id
 * @property CarbonImmutable $captured_on
 * @property int $properties_count
 * @property int $units_count
 * @property int $occupants_count
 * @property int $active_leases_count
 * @property string $rent_month_to_date
 * @property int $maintenance_open_count
 * @property bool $uses_leases
 * @property bool $uses_maintenance
 * @property bool $uses_land
 * @property bool $uses_mpesa_rent
 */
class TenantDailyMetric extends Model
{
    protected $fillable = [
        'organization_id',
        'captured_on',
        'properties_count',
        'units_count',
        'occupants_count',
        'active_leases_count',
        'rent_month_to_date',
        'maintenance_open_count',
        'uses_leases',
        'uses_maintenance',
        'uses_land',
        'uses_mpesa_rent',
    ];

    protected function casts(): array
    {
        return [
            'captured_on' => 'date',
            'rent_month_to_date' => 'decimal:2',
            'uses_leases' => 'boolean',
            'uses_maintenance' => 'boolean',
            'uses_land' => 'boolean',
            'uses_mpesa_rent' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
