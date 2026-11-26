<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\LandParcels\DeleteLandParcelSection;
use App\Actions\LandParcels\EndLandParcelSectionLease;
use App\Actions\LandParcels\LeaseLandParcelSection;
use App\Actions\LandParcels\UpdateLandParcelSectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyLandParcelSectionRequest;
use App\Http\Requests\Tenant\EndLeaseRequest;
use App\Http\Requests\Tenant\LeaseLandParcelSectionRequest;
use App\Http\Requests\Tenant\StoreLandParcelSectionRequest;
use App\Http\Requests\Tenant\UpdateLandParcelSectionRequest;
use App\Models\AgreementTemplate;
use App\Models\LandParcel;
use App\Models\LandParcelSection;
use App\Models\Lease;
use App\Services\StaffService;
use App\Support\DeletionTier;
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

    public function destroy(DestroyLandParcelSectionRequest $request, LandParcel $landParcel, LandParcelSection $landParcelSection, DeleteLandParcelSection $delete): RedirectResponse
    {
        $this->authorizeParcelAccess($request, $landParcel);
        abort_if($landParcelSection->land_parcel_id !== $landParcel->id, 404);

        abort_unless(DeletionTier::allows($request->user(), $landParcel->organization, $landParcelSection), 403);

        $delete->handle($landParcelSection);

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
        abort_if($landParcelSection->activeLease() !== null, 422, 'This section already has an active lease. End it first.');

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

    /**
     * Reserve a vacant section or release a reserved one. Leased sections
     * end their lease instead of changing status here.
     */
    public function updateStatus(
        UpdateLandParcelSectionRequest $request,
        LandParcel $landParcel,
        LandParcelSection $landParcelSection,
        UpdateLandParcelSectionStatus $update,
    ): RedirectResponse {
        $this->authorizeParcelAccess($request, $landParcel);
        abort_if($landParcelSection->land_parcel_id !== $landParcel->id, 404);

        $update->handle($landParcelSection, $request->user(), $request->string('status')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Section {$landParcelSection->refresh()->status}."]);

        return redirect()->route('tenant.land-parcels.show', [
            'land_parcel' => $landParcel->slug,
        ]);
    }

    /**
     * End a section lease early, mirroring unit-lease termination:
     * password-confirmed, section freed back to vacant.
     */
    public function endLease(
        EndLeaseRequest $request,
        LandParcel $landParcel,
        LandParcelSection $landParcelSection,
        EndLandParcelSectionLease $end,
    ): RedirectResponse {
        $this->authorizeParcelAccess($request, $landParcel);
        abort_if($landParcelSection->land_parcel_id !== $landParcel->id, 404);

        $end->handle($landParcel, $landParcelSection, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Section lease ended.']);

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
