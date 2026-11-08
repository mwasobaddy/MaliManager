<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\LandParcels\LeaseLandParcelSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyLandParcelSectionRequest;
use App\Http\Requests\Tenant\LeaseLandParcelSectionRequest;
use App\Http\Requests\Tenant\StoreLandParcelSectionRequest;
use App\Models\AgreementTemplate;
use App\Models\LandParcel;
use App\Models\LandParcelSection;
use App\Models\Lease;
use App\Services\StaffService;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Manages sub-plots of a land parcel (sections) and lets a section be
 * leased independently. Lease creation reuses the same AgreementTemplate
 * merge used for building-unit leases, so parcel sections get a real
 * lease agreement generated from org templates.
 */
class LandParcelSectionController extends Controller
{
    public function store(StoreLandParcelSectionRequest $request, LandParcel $landParcel): RedirectResponse
    {
        $this->authorizeParcelAccess($request, $landParcel);

        $validated = $request->validated();

        $landParcel->sections()->create([
            'name' => $validated['name'],
            'area' => $validated['area'] ?? null,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Section added.']);

        return redirect()->route('tenant.land-parcels.show', [
            'land_parcel' => $landParcel->slug,
        ]);
    }

    public function destroy(DestroyLandParcelSectionRequest $request, LandParcel $landParcel, LandParcelSection $landParcelSection): RedirectResponse
    {
        $this->authorizeParcelAccess($request, $landParcel);
        abort_if($landParcelSection->land_parcel_id !== $landParcel->id, 404);

        $landParcelSection->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Section removed.']);

        return redirect()->route('tenant.land-parcels.show', [
            'land_parcel' => $landParcel->slug,
        ]);
    }

    public function lease(
        LeaseLandParcelSectionRequest $request,
        LandParcel $landParcel,
        LandParcelSection $landParcelSection,
        LeaseLandParcelSection $action,
    ): RedirectResponse {
        $this->authorizeParcelAccess($request, $landParcel);
        abort_if($landParcelSection->land_parcel_id !== $landParcel->id, 404);

        $organization = TenancyContext::organization();
        abort_if($organization === null, 403);

        $lease = $action->handle(
            $landParcelSection,
            $landParcel,
            $organization,
            $request->user(),
            $request->toData(),
        );

        if ($lease->agreement_text) {
            Inertia::flash('agreement_html', $lease->agreement_text);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Lease created for {$landParcelSection->name}.",
        ]);

        return redirect()->route('tenant.land-parcels.show', [
            'land_parcel' => $landParcel->slug,
        ]);
    }

    private function authorizeParcelAccess(Request $request, LandParcel $landParcel): void
    {
        $user = $request->user();
        $organization = TenancyContext::organization();

        if ($user->isOwnerOf($organization)) {
            return;
        }

        if (in_array(
            $landParcel->id,
            app(StaffService::class)->delegatedLandParcelIds($user->membershipFor($organization)),
            true,
        )) {
            return;
        }

        abort(403);
    }
}
