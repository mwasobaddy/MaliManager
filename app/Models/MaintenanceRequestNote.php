<?php

namespace App\Models;

use App\Enums\NoteVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A note attached to a maintenance request.
 *
 * @property int $id
 * @property int $maintenance_request_id
 * @property int|null $author_id
 * @property string $body
 * @property NoteVisibility $visibility
 */
class MaintenanceRequestNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'maintenance_request_id',
        'author_id',
        'body',
        'visibility',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => NoteVisibility::class,
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopeInternal(Builder $query): Builder
    {
        return $query->where('visibility', NoteVisibility::Internal->value);
    }
}
