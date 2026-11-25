<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MoveOutNoticeStatus;
use App\Models\Concerns\LogsTenantActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tenant's 30-day move-out notice against their lease.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $lease_id
 * @property int|null $requested_by
 * @property string $move_out_date
 * @property MoveOutNoticeStatus $status
 * @property string|null $note
 */
class MoveOutNotice extends Model
{
    use HasFactory, LogsTenantActivity;

    protected $fillable = [
        'organization_id',
        'lease_id',
        'requested_by',
        'move_out_date',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => MoveOutNoticeStatus::class,
            'move_out_date' => 'date',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
