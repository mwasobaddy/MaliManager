<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A maintenance request raised by an occupant (active lease) or staff,
 * tracked through an open → assigned → in-progress → resolved → closed
 * workflow against a property (and optionally a specific unit).
 */
class MaintenanceRequest extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    /** @var list<string> */
    public const STATUSES = [
        'opened',
        'assigned',
        'in_progress',
        'resolved',
        'closed',
    ];

    /** @var list<string> */
    public const PRIORITIES = [
        'low',
        'medium',
        'high',
        'urgent',
    ];

    public function registerMediaCollections(): void
    {
        // Photos supplied by the requester or staff, stored under the
        // property's maintenance folder in StorageLayout.
        $this->addMediaCollection('photos');
    }

    protected $fillable = [
        'organization_id',
        'property_id',
        'unit_id',
        'lease_id',
        'occupant_id',
        'raised_by',
        'assigned_to',
        'title',
        'description',
        'status',
        'priority',
        'resolved_at',
        'resolution_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function occupant(): BelongsTo
    {
        return $this->belongsTo(Occupant::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }
}
