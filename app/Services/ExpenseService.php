<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\LandParcel;
use App\Models\Property;
use App\Models\User;
use App\Support\TenancyContext;

/**
 * Records money spent against an asset (Property or LandParcel).
 * Receipts are attached by the controller after the service call,
 * following the established media pattern.
 */
class ExpenseService extends Service
{
    /**
     * Validate that the target asset belongs to the organization and
     * normalize the expenseable target.
     *
     * @param  array<string, mixed>  $data
     * @return array{asset: Property|LandParcel, unitId: ?int}
     */
    public function resolveTarget(User $actor, array $data): array
    {
        $type = $data['expenseable_type'];
        $id = (int) $data['expenseable_id'];

        $assetClass = match ($type) {
            'property' => Property::class,
            'land_parcel' => LandParcel::class,
            default => abort(422, 'Invalid expense target.'),
        };

        /** @var Property|LandParcel|null $asset */
        $asset = $assetClass::query()->find($id);

        if (! $asset || $asset->organization_id !== ($data['organization_id'] ?? null)) {
            abort(422, 'The selected asset does not belong to this organization.');
        }

        $unitId = null;

        if ($asset instanceof Property && ! empty($data['unit_id'])) {
            $unit = $asset->units()->whereKey((int) $data['unit_id'])->first();
            abort_unless($unit, 422, 'The selected unit does not belong to this property.');

            $unitId = $unit->id;
        }

        return ['asset' => $asset, 'unitId' => $unitId];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, int $organizationId, array $data): Expense
    {
        return $this->transaction(function () use ($actor, $organizationId, $data) {
            ['asset' => $asset, 'unitId' => $unitId] = $this->resolveTarget($actor, $data + ['organization_id' => $organizationId]);

            $expense = new Expense([
                'tenant_id' => $this->tenantId(),
                'organization_id' => $organizationId,
                'category' => $data['category'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'KES',
                'spent_on' => $data['spent_on'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $expense->expenseable()->associate($asset);
            $expense->unit_id = $unitId;

            return $this->save($expense);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Expense $expense, User $actor, array $data): Expense
    {
        return $this->transaction(function () use ($expense, $actor, $data) {
            $resolved = $this->resolveTarget($actor, $data + ['organization_id' => $expense->organization_id]);

            $expense->fill([
                'category' => $data['category'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? $expense->currency,
                'spent_on' => $data['spent_on'],
                'notes' => $data['notes'] ?? null,
                'unit_id' => $resolved['unitId'],
            ]);
            $expense->expenseable()->associate($resolved['asset']);

            return $this->save($expense);
        });
    }

    public function softDelete(Expense $expense): void
    {
        $this->transaction(function () use ($expense) {
            $this->delete($expense);
        });
    }

    private function tenantId(): ?string
    {
        return TenancyContext::tenantId();
    }
}
