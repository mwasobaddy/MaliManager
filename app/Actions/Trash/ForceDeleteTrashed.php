<?php

namespace App\Actions\Trash;

use App\Actions\Roles\DeleteRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\Service;
use App\Services\TrashService;
use App\Support\TrashModules;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Permanently removes soft-deleted rows.
 *
 * Tier-scoped like everything else in the trash: the row must be visible
 * to the actor first. Media attached to photos-carrying models is cleared
 * first so a permanent delete leaves no orphaned files behind.
 */
class ForceDeleteTrashed extends Service
{
    /**
     * @return array{deleted: list<int>, skipped: list<array{id: int, reason: string}>}
     */
    public function handle(User $actor, ?Organization $organization, string $context, string $moduleKey, array $ids): array
    {
        $deleted = [];
        $skipped = [];

        $modules = $context === 'tenant' ? TrashModules::tenant() : TrashModules::platform();
        $module = $modules[$moduleKey] ?? null;

        if ($module === null) {
            foreach ($ids as $id) {
                $skipped[] = ['id' => (int) $id, 'reason' => 'Unknown trash module.'];
            }

            return ['deleted' => $deleted, 'skipped' => $skipped];
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

            // Roles keep their users and permissions while trashed, so the
            // permanent delete reassigns holders to searcher first — the
            // same safety the old destroy carried.
            if ($moduleKey === 'roles') {
                app(DeleteRole::class)->force($model);
                $deleted[] = $id;

                continue;
            }

            $this->transaction(function () use ($model): void {
                if (in_array(InteractsWithMedia::class, class_uses_recursive($model))) {
                    $model->clearMediaCollection();
                }

                $model->forceDelete();
            });

            $deleted[] = $id;
        }

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }
}
