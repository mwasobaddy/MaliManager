<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A fixed, platform-wide catalog entry of a sub-permission
 * (e.g. "user.manage", "property.create"). Sub-roles reference
 * these keys to grant access inside an organization.
 *
 * @property int $id
 * @property string $key
 * @property string $module
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 */
class SubPermission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'module',
        'name',
        'description',
        'sort_order',
        'created_by',
    ];

    public function subRoles(): BelongsToMany
    {
        return $this->belongsToMany(SubRole::class, 'sub_role_sub_permission')->withTimestamps();
    }
}
