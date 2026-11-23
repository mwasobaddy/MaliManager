<?php

namespace App\Services;

use App\Enums\SubPermissionKey;
use App\Models\Organization;
use App\Models\User;
use App\Support\TrashModules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reads trashed rows with the same tier scoping everywhere.
 *
 * Platform admins see everything, owners see their whole organization,
 * everyone else sees only rows they created (`created_by`). The tier is
 * the only thing that varies by actor — the organization fence never
 * lifts, so a staff member can never list another org's trash.
 */
class TrashService
{
    /**
     * Tenant modules the actor may see trash for (has the destroy gate).
     *
     * @return array<string, array{model: class-string, label: string, singular: string, permission: string, scope: string}>
     */
    public function tenantModulesFor(User $user, Organization $organization): array
    {
        return collect(TrashModules::tenant())
            ->filter(fn (array $module): bool => $user->hasSubPermission($organization, SubPermissionKey::from($module['permission'])))
            ->all();
    }

    /**
     * Platform modules the actor may see trash for (holds the delete gate).
     *
     * @return array<string, array{model: class-string, label: string, singular: string, permission: string, scope: string}>
     */
    public function platformModulesFor(User $user): array
    {
        if ($user->isPlatformAdmin()) {
            return TrashModules::platform();
        }

        return collect(TrashModules::platform())
            ->filter(fn (array $module): bool => $user->can($module['permission']))
            ->all();
    }

    /**
     * Trashed counts per tenant module, tier-scoped.
     *
     * @return array<string, int>
     */
    public function tenantCounts(User $user, Organization $organization): array
    {
        $counts = [];

        foreach ($this->tenantModulesFor($user, $organization) as $key => $module) {
            $counts[$key] = $this->tenantQuery($module, $user, $organization)->count();
        }

        return $counts;
    }

    /**
     * Trashed counts per platform module, tier-scoped.
     *
     * @return array<string, int>
     */
    public function platformCounts(User $user): array
    {
        $counts = [];

        foreach ($this->platformModulesFor($user) as $key => $module) {
            $counts[$key] = $this->platformQuery($module, $user)->count();
        }

        return $counts;
    }

    /**
     * One page of trashed rows for a tenant module.
     */
    public function tenantItems(User $user, Organization $organization, string $moduleKey, int $perPage = 15): LengthAwarePaginator
    {
        $module = $this->tenantModulesFor($user, $organization)[$moduleKey] ?? null;

        abort_if($module === null, 403);

        return $this->tenantQuery($module, $user, $organization)
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($model): array => $this->transform($model, $moduleKey));
    }

    /**
     * One page of trashed rows for a platform module.
     */
    public function platformItems(User $user, string $moduleKey, int $perPage = 15): LengthAwarePaginator
    {
        $module = $this->platformModulesFor($user)[$moduleKey] ?? null;

        abort_if($module === null, 403);

        return $this->platformQuery($module, $user)
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($model): array => $this->transform($model, $moduleKey));
    }

    /**
     * Base trashed query for a tenant module: organization fence plus tier.
     */
    public function tenantQuery(array $module, User $user, Organization $organization): Builder
    {
        /** @var Model $model */
        $model = new ($module['model']);

        $query = $model->newQuery()->onlyTrashed();

        match ($module['scope']) {
            'direct' => $query->where($model->getTable().'.organization_id', $organization->id),
            // Parents carry SoftDeletes too: a trashed parcel must not hide
            // its sections from the trash they already sit in. Restore still
            // refuses until the parent is back (see RestoreTrashed).
            'via_property' => $query->whereHas('property', fn (Builder $property) => $property->withTrashed()->where('organization_id', $organization->id)),
            'via_parcel' => $query->whereHas('parcel', fn (Builder $parcel) => $parcel->withTrashed()->where('organization_id', $organization->id)),
            'staff' => $query->whereIn('users.created_by', $this->organizationUserIds($organization)),
            default => null,
        };

        $this->applyTier($query, $user, $organization, $model->getTable());

        return $query;
    }

    /**
     * Base trashed query for a platform module: tier only, no org fence.
     */
    public function platformQuery(array $module, User $user): Builder
    {
        /** @var Model $model */
        $model = new ($module['model']);

        $query = $model->newQuery()->onlyTrashed();

        if (! $user->isPlatformAdmin()) {
            $query->where($model->getTable().'.created_by', $user->id);
        }

        return $query;
    }

    /**
     * Tier fence: admins and owners see everything in scope, everyone else
     * sees only rows they created. Staff trash has no per-row creator
     * column on the pivot (memberships are hard-deleted), so it lists by
     * org membership instead and the tier applies at restore time.
     */
    private function applyTier(Builder $query, User $user, Organization $organization, string $table): void
    {
        if ($user->isPlatformAdmin() || $user->isOwnerOf($organization)) {
            return;
        }

        // Staff scope already narrows to the org's people; the per-row
        // tier would hide colleagues an admin-like staffer should empty.
        if ($table === 'users') {
            return;
        }

        $query->where($table.'.created_by', $user->id);
    }

    /**
     * Ids of everyone currently in the organization — the staff-trash
     * population, since memberships are hard-deleted and the User row
     * itself carries no organization_id.
     *
     * @return list<int>
     */
    private function organizationUserIds(Organization $organization): array
    {
        return $organization->users()->pluck('users.id')->all();
    }

    /**
     * Plain arrays for Inertia: id, human display, when it was trashed.
     *
     * @param  Model  $model
     */
    private function transform($model, string $moduleKey): array
    {
        return [
            'id' => $model->getKey(),
            'display' => $this->displayFor($model, $moduleKey),
            'deleted_at' => $model->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * Best-effort human label per model. Tries name, then title, then
     * email, then reference, then falls back to "type #id" — never blank.
     *
     * @param  Model  $model
     */
    private function displayFor($model, string $moduleKey): string
    {
        foreach (['name', 'title', 'email', 'reference', 'slug'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return str_replace('-', ' ', $moduleKey).' #'.$model->getKey();
    }
}
