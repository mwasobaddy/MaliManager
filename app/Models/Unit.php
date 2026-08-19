<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A rentable unit inside a property.
 *
 * @property int $id
 * @property int $property_id
 * @property string $name
 * @property string $status
 * @property string|null $type
 * @property float|null $monthly_rent
 * @property float|null $deposit
 * @property array|null $settings
 * @property int|null $created_by
 */
class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'property_id',
        'name',
        'status',
        'type',
        'monthly_rent',
        'deposit',
        'settings',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_rent' => 'decimal:2',
            'deposit' => 'decimal:2',
            'settings' => 'array',
        ];
    }

    /** @use HasFactory<UnitFactory> */
    protected static function newFactory(): UnitFactory
    {
        return UnitFactory::new();
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function occupants(): BelongsToMany
    {
        return $this->belongsToMany(Occupant::class, 'occupant_unit')
            ->withPivot('created_by')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
