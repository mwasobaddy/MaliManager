<?php

namespace App\Actions\Trash;

use App\Models\Organization;
use App\Models\User;
use App\Services\Service;
use App\Services\TrashService;
use App\Support\TrashModules;

/**
 * Empties one trash module in scope — the "empty their trash" path for
 * owners and admins. Reuses the force-delete action per row so media
 * cleanup and tier scoping stay identical to single-row deletes.
 */
class EmptyTrashModule extends Service
{
    /**
     * @return array{deleted: list<int>, skipped: list<array{id: int, reason: string}>}
     */
    public function handle(User $actor, ?Organization $organization, string $context, string $moduleKey): array
    {
        $service = app(TrashService::class);

        $query = $context === 'tenant'
            ? $service->tenantQuery(
                (fn (): array => TrashModules::tenant()[$moduleKey] ?? abort(422, 'Unknown trash module.'))(),
                $actor,
                $organization,
            )
            : $service->platformQuery(
                (fn (): array => TrashModules::platform()[$moduleKey] ?? abort(422, 'Unknown trash module.'))(),
                $actor,
            );

        $ids = $query->pluck($query->getModel()->getTable().'.id')->map(fn ($id): int => (int) $id)->all();

        if ($ids === []) {
            return ['deleted' => [], 'skipped' => []];
        }

        return app(ForceDeleteTrashed::class)->handle($actor, $organization, $context, $moduleKey, $ids);
    }
}
