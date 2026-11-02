<?php

namespace App\Models;

use App\Enums\PlatformRole;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * The role list predicate: name search, exact status, and the built-in vs
     * custom split. Shared by the index and the CSV export.
     *
     * @param  array{search?: string, status?: string, type?: string}  $filters
     */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                ($filters['search'] ?? '') !== '',
                fn (Builder $q) => $q->where('name', 'like', "%{$filters['search']}%"),
            )
            ->when(
                ($filters['status'] ?? '') !== '',
                fn (Builder $q) => $q->where('is_active', $filters['status'] === 'active'),
            )
            ->when(($filters['type'] ?? '') !== '', function (Builder $q) use ($filters): void {
                // "default" is the built-in four — the rows whose name is an enum
                // case. `Role::isProtected()` is the same test, but it is PHP, so
                // the split has to be expressed in SQL as its negation.
                $builtIn = array_column(PlatformRole::cases(), 'value');

                $filters['type'] === 'default'
                    ? $q->whereIn('name', $builtIn)
                    : $q->whereNotIn('name', $builtIn);
            });
    }
}
