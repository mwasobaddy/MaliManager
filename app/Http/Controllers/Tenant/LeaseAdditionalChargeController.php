<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Leases\CreateLeaseAdditionalCharge;
use App\Actions\Leases\DeleteLeaseAdditionalCharge;
use App\Actions\Leases\UpdateLeaseAdditionalCharge;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyLeaseAdditionalChargeRequest;
use App\Http\Requests\Tenant\StoreLeaseAdditionalChargeRequest;
use App\Http\Requests\Tenant\UpdateLeaseAdditionalChargeRequest;
use App\Models\Lease;
use App\Models\LeaseAdditionalCharge;
use App\Models\Property;
use App\Services\StaffService;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Free-form extras on one lease (parking, pets, tenant-specific fees).
 * The list lives in the lease view sheet; these endpoints only mutate it.
 */
class LeaseAdditionalChargeController extends Controller
{
    public function store(StoreLeaseAdditionalChargeRequest $request, Property $property, Lease $lease, CreateLeaseAdditionalCharge $create): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($lease->property_id !== $property->id, 403);

        $create->handle($request->user(), TenancyContext::organization()->refresh(), $lease, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Additional charge added.']);

        return back();
    }

    public function update(UpdateLeaseAdditionalChargeRequest $request, Property $property, Lease $lease, LeaseAdditionalCharge $extra, UpdateLeaseAdditionalCharge $update): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($lease->property_id !== $property->id || $extra->lease_id !== $lease->id, 403);

        $update->handle($extra, $lease, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Additional charge updated.']);

        return back();
    }

    public function destroy(DestroyLeaseAdditionalChargeRequest $request, Property $property, Lease $lease, LeaseAdditionalCharge $extra, DeleteLeaseAdditionalCharge $delete): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($lease->property_id !== $property->id || $extra->lease_id !== $lease->id, 403);

        $delete->handle($extra, $lease, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Additional charge deleted.']);

        return back();
    }

    private function authorizePropertyAccess(Request $request, Property $property): void
    {
        $user = $request->user();

        if ($user->isOwnerOf($property->organization)) {
            return;
        }

        $membership = $user->membershipFor($property->organization);

        if ($membership && in_array($property->id, app(StaffService::class)->delegatedPropertyIds($membership))) {
            return;
        }

        abort(403);
    }
}
