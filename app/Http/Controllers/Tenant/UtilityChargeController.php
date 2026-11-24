<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Metering\CreateUtilityCharge;
use App\Actions\Metering\DeleteUtilityCharge;
use App\Actions\Metering\UpdateUtilityCharge;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyUtilityChargeRequest;
use App\Http\Requests\Tenant\StoreUtilityChargeRequest;
use App\Http\Requests\Tenant\UpdateUtilityChargeRequest;
use App\Models\Property;
use App\Models\UtilityCharge;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Landlord-configured utility charges: metered rates, fixed per-unit fees
 * and one-time fees, assigned to the units whose occupants pay them. The
 * list lives on the utilities page; these endpoints only mutate it.
 */
class UtilityChargeController extends Controller
{
    public function store(StoreUtilityChargeRequest $request, CreateUtilityCharge $create): RedirectResponse
    {
        $property = $this->property((int) $request->integer('property_id'));

        $create->handle($request->user(), TenancyContext::organization()->refresh(), $property, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utility charge added.']);

        return back();
    }

    public function update(UpdateUtilityChargeRequest $request, UtilityCharge $charge, UpdateUtilityCharge $update): RedirectResponse
    {
        $this->scope($charge);

        $update->handle(
            $charge,
            $charge->property,
            $request->user(),
            $request->validated()
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utility charge updated.']);

        return back();
    }

    public function destroy(DestroyUtilityChargeRequest $request, UtilityCharge $charge, DeleteUtilityCharge $delete): RedirectResponse
    {
        $this->scope($charge);

        $delete->handle($charge, $charge->property, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utility charge deleted.']);

        return back();
    }

    private function property(int $propertyId): Property
    {
        $property = Property::query()->whereKey($propertyId)->first();

        abort_unless($property !== null && $property->organization_id === TenancyContext::organization()?->id, 404);

        return $property;
    }

    private function scope(UtilityCharge $charge): void
    {
        abort_unless($charge->organization_id === TenancyContext::organization()?->id, 404);
    }
}
