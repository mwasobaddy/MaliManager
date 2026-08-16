<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Per-asset delegation. Grants a user's organization membership
 * (with its sub-role) access to a specific property, unit, or
 * land parcel instead of the whole organization.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $organization_user_id
 * @property string $delegatable_type
 * @property int $delegatable_id
 */
class Delegation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'organization_user_id',
        'delegatable_type',
        'delegatable_id',
        'created_by',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function organizationUser(): BelongsTo
    {
        return $this->belongsTo(OrganizationUser::class);
    }

    public function delegatable(): MorphTo
    {
        return $this->morphTo();
    }
}
