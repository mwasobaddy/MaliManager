<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Membership of a user in an organization.
 *
 * @property int $organization_id
 * @property int $user_id
 * @property int|null $sub_role_id
 * @property bool $is_owner
 * @property string $status
 */
class OrganizationUser extends Pivot
{
    protected $table = 'organization_user';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $casts = [
        'is_owner' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subRole(): BelongsTo
    {
        return $this->belongsTo(SubRole::class);
    }

    public function delegations(): HasMany
    {
        return $this->hasMany(Delegation::class);
    }
}
