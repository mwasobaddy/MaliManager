<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A database-configurable pricing tier. Limits are not hardcoded.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $currency
 * @property int $price
 * @property int|null $properties_limit
 * @property int|null $units_limit
 * @property bool $has_dedicated_db
 * @property bool $has_custom_domain
 * @property bool $has_email_notifications
 * @property bool $has_sms_notifications
 * @property array|null $features
 * @property bool $is_active
 * @property int $sort_order
 */
class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'currency',
        'price',
        'properties_limit',
        'units_limit',
        'has_dedicated_db',
        'has_custom_domain',
        'has_email_notifications',
        'has_sms_notifications',
        'features',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'properties_limit' => 'integer',
            'units_limit' => 'integer',
            'has_dedicated_db' => 'boolean',
            'has_custom_domain' => 'boolean',
            'has_email_notifications' => 'boolean',
            'has_sms_notifications' => 'boolean',
            'features' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }
}
