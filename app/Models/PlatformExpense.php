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
 * A platform-level operating cost (hosting, domains, tooling, etc.), tracked
 * separately from per-organization asset expenses. Central, non-tenant-scoped.
 */
class PlatformExpense extends Model
{
    use HasFactory, LogsPlatformActivity, SoftDeletes;

    /** @var list<string> */
    public const CATEGORIES = [
        'hosting',
        'domain',
        'tooling',
        'software',
        'marketing',
        'other',
    ];

    protected $fillable = [
        'category',
        'amount',
        'currency',
        'spent_on',
        'vendor_name',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_on' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The admin list predicate: search across category, vendor and notes, plus
     * an exact category and an inclusive spent-on range. Shared by the index and
     * the CSV export.
     *
     * @param  array{search?: string, category?: string, from?: string, to?: string}  $filters
     */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                ($filters['search'] ?? '') !== '',
                fn (Builder $q) => $q->where(function (Builder $inner) use ($filters): void {
                    $inner->where('category', 'like', "%{$filters['search']}%")
                        ->orWhere('vendor_name', 'like', "%{$filters['search']}%")
                        ->orWhere('notes', 'like', "%{$filters['search']}%");
                }),
            )
            ->when(
                ($filters['category'] ?? '') !== '',
                fn (Builder $q) => $q->where('category', $filters['category']),
            )
            ->when(
                ($filters['from'] ?? '') !== '',
                fn (Builder $q) => $q->whereDate('spent_on', '>=', $filters['from']),
            )
            ->when(
                ($filters['to'] ?? '') !== '',
                fn (Builder $q) => $q->whereDate('spent_on', '<=', $filters['to']),
            );
    }
}
