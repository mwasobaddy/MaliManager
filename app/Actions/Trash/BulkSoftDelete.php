<?php

namespace App\Actions\Trash;

use App\Actions\LandParcels\DeleteLandParcelSection;
use App\Actions\Roles\DeleteRole;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\InspectionService;
use App\Services\LandParcelService;
use App\Services\MaintenanceRequestService;
use App\Services\OccupantService;
use App\Services\OrganizationManagementService;
use App\Services\PaymentService;
use App\Services\Service;
use App\Services\StaffService;
use App\Services\UserService;
use App\Support\TrashModules;
use Illuminate\Database\Eloquent\Model;

/**
 * Soft-deletes many rows at once, reusing each module's single-item
 * delete path so bulk can never delete what the row action would refuse.
 *
 * An occupied unit is skipped with a reason instead of failing the whole
 * batch — the same 422 the single delete carries, reported per row. Staff
 * goes through DeleteStaff (membership removed, login soft-deleted only
 * when it was the last organization).
 */
class BulkSoftDelete extends Service
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

        $modelClass = $module['model'];

        foreach (array_unique(array_map('intval', $ids)) as $id) {
            /** @var Model|null $model */
            $model = $modelClass::query()->where('id', $id)->first();

            if ($model === null) {
                $skipped[] = ['id' => $id, 'reason' => 'Not found.'];

                continue;
            }

            if (! $this->inScope($module, $model, $actor, $organization)) {
                $skipped[] = ['id' => $id, 'reason' => 'Not found in your scope.'];

                continue;
            }

            $refusal = $this->refusalReason($context, $moduleKey, $model);

            if ($refusal !== null) {
                $skipped[] = ['id' => $id, 'reason' => $refusal];

                continue;
            }

            try {
                $this->deleteOne($context, $moduleKey, $model, $actor, $organization);
            } catch (\Throwable $e) {
                report($e);
                $skipped[] = ['id' => $id, 'reason' => 'Could not delete this row.'];

                continue;
            }

            $deleted[] = $id;
        }

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }

    private function inScope(array $module, $model, User $actor, ?Organization $organization): bool
    {
        if ($organization !== null && $module['scope'] === 'direct') {
            if ((int) $model->getAttribute('organization_id') !== (int) $organization->id) {
                return false;
            }
        }

        if ($organization !== null && $module['scope'] === 'via_property') {
            // Properties carry SoftDeletes: a trashed parent must not hide
            // its units from the trash they already sit in.
            $propertyOrg = $model->property()->withTrashed()->first()?->organization_id;

            if ((int) $propertyOrg !== (int) $organization->id) {
                return false;
            }
        }

        if ($organization !== null && $module['scope'] === 'via_parcel') {
            // Same as units: a trashed parcel must not hide its sections.
            $parcelOrg = $model->parcel()->withTrashed()->first()?->organization_id;

            if ((int) $parcelOrg !== (int) $organization->id) {
                return false;
            }
        }

        if ($organization !== null && $module['scope'] === 'staff') {
            return $model->organizations()->where('organizations.id', $organization->id)->exists();
        }

        if ($organization === null && ! $actor->isPlatformAdmin()) {
            // Roles are gate-tiered (no creator column) — see TrashModules.
            if ($moduleKey === 'roles') {
                return true;
            }

            return (int) ($model->getAttribute('created_by') ?? 0) === (int) $actor->id;
        }

        if ($organization !== null && ! ($actor->isPlatformAdmin() || $actor->isOwnerOf($organization))) {
            if ($module['scope'] !== 'staff' && (int) ($model->getAttribute('created_by') ?? 0) !== (int) $actor->id) {
                return false;
            }
        }

        return true;
    }

    private function refusalReason(string $context, string $moduleKey, $model): ?string
    {
        if ($model->trashed()) {
            return 'Already in the trash.';
        }

        if ($moduleKey === 'roles' && $model->isProtected()) {
            return 'Built-in roles cannot be deleted.';
        }

        if ($moduleKey === 'units') {
            $occupied = Lease::query()
                ->where('unit_id', $model->getKey())
                ->where('status', 'active')
                ->exists();

            if ($occupied) {
                return 'Occupied — end its lease first.';
            }
        }

        return null;
    }

    private function deleteOne(string $context, string $moduleKey, $model, User $actor, ?Organization $organization): void
    {
        if ($context === 'platform') {
            match ($moduleKey) {
                'organizations' => app(OrganizationManagementService::class)->deleteOrganization($model, $actor),
                'users' => app(UserService::class)->deleteUser($model, $actor),
                'roles' => $this->deleteRole($model),
                default => $model->delete(),
            };

            return;
        }

        match ($moduleKey) {
            'occupants' => app(OccupantService::class)->softDelete($model),
            'inspections' => app(InspectionService::class)->softDelete($model),
            'land-parcels' => app(LandParcelService::class)->softDelete($model),
            'land-parcel-sections' => app(DeleteLandParcelSection::class)->handle($model),
            'payments' => app(PaymentService::class)->softDelete($model),
            'expenses' => app(ExpenseService::class)->softDelete($model),
            'maintenance' => app(MaintenanceRequestService::class)->softDelete($model),
            'staff' => app(StaffService::class)->softDelete($organization, $model, $actor),
            default => $model->delete(),
        };
    }

    /**
     * Soft-delete a role through the same action as the single delete, so
     * the protected guard and the permission-cache flush can never drift.
     */
    private function deleteRole($model): void
    {
        app(DeleteRole::class)->handle($model);
    }
}
