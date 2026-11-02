<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyLandParcelSectionRequest;
use App\Http\Requests\Tenant\LeaseLandParcelSectionRequest;
use App\Http\Requests\Tenant\StoreLandParcelSectionRequest;
use App\Models\AgreementTemplate;
use App\Models\LandParcel;
use App\Models\LandParcelSection;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\Person;
use App\Services\StaffService;
use App\Support\LeaseAgreementTemplate;
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

    public function lease(LeaseLandParcelSectionRequest $request, LandParcel $landParcel, LandParcelSection $landParcelSection): RedirectResponse
    {
        $this->authorizeParcelAccess($request, $landParcel);
        abort_if($landParcelSection->land_parcel_id !== $landParcel->id, 404);

        $organization = TenancyContext::organization();

        $validated = $request->validated();

        $person = $this->resolvePerson($request, $validated);

        $lease = new Lease([
            'person_id' => $person->id,
            'organization_id' => $organization->id,
            'tenant_id' => TenancyContext::tenantId(),
            'land_parcel_id' => $landParcel->id,
            'land_parcel_section_id' => $landParcelSection->id,
            'rent_amount' => $validated['rent_amount'],
            'rent_frequency' => $validated['rent_frequency'] ?? null,
            'currency' => $validated['currency'] ?? null,
            'deposit' => $validated['deposit'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);
        $lease->save();

        $agreementText = $this->generateAgreement($organization, $lease, $validated['template_id'] ?? null);

        if ($agreementText !== null) {
            $lease->update(['agreement_text' => $agreementText]);
            Inertia::flash('agreement_html', $agreementText);
        }

        $landParcelSection->update(['status' => 'leased']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Lease created for {$landParcelSection->name}.",
        ]);

        return redirect()->route('tenant.land-parcels.show', [
            'land_parcel' => $landParcel->slug,
        ]);
    }

    private function resolvePerson(Request $request, array $data): Person
    {
        if (! empty($data['person_id'])) {
            return Person::findOrFail($data['person_id']);
        }

        return Person::firstOrCreate(
            ['email' => $data['tenant_email']],
            [
                'first_name' => $data['tenant_name'],
                'last_name' => '',
                'created_by' => $request->user()->id,
            ],
        );
    }

    private function generateAgreement(Organization $organization, Lease $lease, ?int $templateId): ?string
    {
        $template = null;

        if ($templateId) {
            $template = AgreementTemplate::where('organization_id', $organization->id)
                ->where('id', $templateId)
                ->first();
        }

        if (! $template) {
            $template = AgreementTemplate::where('organization_id', $organization->id)
                ->whereNull('property_id')
                ->first();
        }

        if (! $template) {
            return null;
        }

        return LeaseAgreementTemplate::render($template->body_html, $lease);
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
