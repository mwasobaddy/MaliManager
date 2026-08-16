<?php

namespace App\Models;

use App\Enums\SubPermissionKey;
use Database\Factories\SubRoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An organization-scoped role that bundles a set of sub-permissions.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $status
 */
class SubRole extends Model
{
    /** @use HasFactory<SubRoleFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'status',
        'created_by',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subPermissions(): BelongsToMany
    {
        return $this->belongsToMany(SubPermission::class, 'sub_role_sub_permission')->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasSubPermissionFor(SubPermissionKey $key): bool
    {
        return $this->subPermissions()->where('key', $key->value)->exists();
    }
}
