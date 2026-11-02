<?php

namespace App\Services;

use App\Actions\Expenses\Concerns\ExpenseHelpers;
use App\Actions\Expenses\CreateExpense;
use App\Actions\Expenses\DeleteExpense;
use App\Actions\Expenses\UpdateExpense;
use App\Models\Expense;
use App\Models\LandParcel;
use App\Models\Property;
use App\Models\User;

/**
 * Records money spent against an asset (Property or LandParcel).
 * Receipts are attached by the controller after the service call,
 * following the established media pattern.
 */
class ExpenseService extends Service
{
    use ExpenseHelpers;

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
        return app(CreateExpense::class)->handle($actor, $organizationId, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Expense $expense, User $actor, array $data): Expense
    {
        return app(UpdateExpense::class)->handle($expense, $actor, $data);
    }

    public function softDelete(Expense $expense): void
    {
        app(DeleteExpense::class)->handle($expense);
    }
}
