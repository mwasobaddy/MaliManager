<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a lease's rent history: the rent that applies from
 * `effective_date` until the next change (or today). The inception row is
 * seeded at lease creation (and backfilled from the pre-amendment rate on
 * the first amendment of an older lease), so arrears always price each
 * period at the rate that was actually in effect.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $lease_id
 * @property Carbon $effective_date
 * @property string $rent_amount
 */
class LeaseRentChange extends Model
{
    protected $fillable = [
        'organization_id',
        'lease_id',
        'effective_date',
        'rent_amount',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'rent_amount' => 'decimal:2',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
