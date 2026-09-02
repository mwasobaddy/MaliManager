<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsPlatformActivity;
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
}
