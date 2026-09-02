<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsPlatformActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A payment received from an organization for their subscription (income).
 * Central, non-tenant-scoped record used by the platform revenue graphs.
 */
class SubscriptionPayment extends Model
{
    use HasFactory, LogsPlatformActivity, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'plan_id',
        'amount',
        'currency',
        'received_on',
        'period_start',
        'period_end',
        'method',
        'reference',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_on' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The distinct years that have subscription income, oldest first.
     *
     * @return list<int>
     */
    public static function availableYears(): array
    {
        $years = self::query()
            ->withoutTrashed()
            ->pluck('received_on')
            ->map(fn ($date) => (int) $date->format('Y'))
            ->unique()
            ->sort()
            ->values()
            ->push((int) date('Y'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $years;
    }
}
