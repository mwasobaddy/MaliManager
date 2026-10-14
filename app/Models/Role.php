<?php

namespace App\Models;

use App\Enums\PlatformRole;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A platform role.
 *
 * Extends Spatie's model for two reasons: `is_active` has to be fillable, and
 * the protected/custom distinction needs a home that both the controller and
 * the tests can ask about.
 *
 * @property string $name
 * @property string $guard_name
 * @property bool $is_active
 * @property-read Collection<Permission> $permissions
 */
class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * The built-in roles an admin cannot delete or deactivate.
     *
     * `admin` in particular: removing it would lock everyone out of the
     * platform, since `EnsureUserIsPlatformAdmin` gates on this exact name.
     */
    public function isProtected(): bool
    {
        return PlatformRole::tryFrom($this->name) !== null;
    }

    /**
     * How many user accounts currently hold this role.
     */
    public function holderCount(): int
    {
        return $this->users()->count();
    }
}
