<?php

namespace App\Actions\Trash;

use App\Models\Organization;
use App\Models\User;
use App\Services\Service;
use App\Services\TrashService;
use App\Support\TrashModules;

/**
 * Restores soft-deleted rows back to their lists.
 *
 * The row must be visible in the actor's tier first — restore can never
 * widen who may touch a row. Guards mirror the delete-time invariants:
 * a section needs its parent parcel back first, an organization needs
 * its slug still free, a staff restore revives the login only (team
 * access needs a fresh invite, matching the re-activate path).
 */
class RestoreTrashed extends Service
{
    /**
     * @return array{restored: list<int>, skipped: list<array{id: int, reason: string}>}
     */
    public function handle(User $actor, ?Organization $organization, string $context, string $moduleKey, array $ids): array
    {
        $restored = [];
        $skipped = [];

        $modules = $context === 'tenant' ? TrashModules::tenant() : TrashModules::platform();
        $module = $modules[$moduleKey] ?? null;

        if ($module === null) {
            foreach ($ids as $id) {
                $skipped[] = ['id' => (int) $id, 'reason' => 'Unknown trash module.'];
            }

            return ['restored' => $restored, 'skipped' => $skipped];
        }

        $service = app(TrashService::class);

        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $query = $context === 'tenant'
                ? $service->tenantQuery($module, $actor, $organization)
                : $service->platformQuery($module, $actor);

            $model = $query->where($query->getModel()->getTable().'.id', $id)->first();

            if ($model === null) {
                $skipped[] = ['id' => $id, 'reason' => 'Not found in your trash.'];

                continue;
            }

            $refusal = $this->refusalReason($moduleKey, $model);

            if ($refusal !== null) {
                $skipped[] = ['id' => $id, 'reason' => $refusal];

                continue;
            }

            $this->transaction(function () use ($model): void {
                $model->restore();
            });

            $restored[] = $id;
        }

        return ['restored' => $restored, 'skipped' => $skipped];
    }

    private function refusalReason(string $moduleKey, $model): ?string
    {
        if ($moduleKey === 'land-parcel-sections') {
            $parcel = $model->parcel()->withTrashed()->first();

            if ($parcel === null || $parcel->trashed()) {
                return 'Restore its land parcel first.';
            }
        }

        if ($moduleKey === 'organizations') {
            $clash = Organization::query()
                ->where('slug', $model->slug)
                ->where('id', '!=', $model->getKey())
                ->exists();

            if ($clash) {
                return 'Another organization already uses this slug.';
            }
        }

        if ($moduleKey === 'users' || $moduleKey === 'staff') {
            $clash = User::query()
                ->where('email', $model->email)
                ->where('id', '!=', $model->getKey())
                ->exists();

            if ($clash) {
                return 'Another account already uses this email.';
            }
        }

        return null;
    }
}
