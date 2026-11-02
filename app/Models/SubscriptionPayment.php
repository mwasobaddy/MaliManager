<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsPlatformActivity;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * The admin list predicate: search across reference, method, notes and the
     * organization name, plus an inclusive received-on range. Shared by the index
     * and the CSV export.
     *
     * @param  array{search?: string, from?: string, to?: string}  $filters
     */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                ($filters['search'] ?? '') !== '',
                fn (Builder $q) => $q->where(function (Builder $inner) use ($filters): void {
                    $inner->where('reference', 'like', "%{$filters['search']}%")
                        ->orWhere('method', 'like', "%{$filters['search']}%")
                        ->orWhere('notes', 'like', "%{$filters['search']}%")
                        ->orWhereHas('organization', fn (Builder $org) => $org->where('name', 'like', "%{$filters['search']}%"));
                }),
            )
            ->when(
                ($filters['from'] ?? '') !== '',
                fn (Builder $q) => $q->whereDate('received_on', '>=', $filters['from']),
            )
            ->when(
                ($filters['to'] ?? '') !== '',
                fn (Builder $q) => $q->whereDate('received_on', '<=', $filters['to']),
            );
    }
}
