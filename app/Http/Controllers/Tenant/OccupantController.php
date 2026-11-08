<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyOccupantRequest;
use App\Http\Requests\Tenant\MoveOutOccupantRequest;
use App\Http\Requests\Tenant\OccupantAccessRequest;
use App\Http\Requests\Tenant\StoreOccupantRequest;
use App\Http\Requests\Tenant\UpdateOccupantRequest;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Property;
use App\Services\OccupantService;
use App\Services\StaffService;
use App\Support\LeaseAgreementTemplate;
use App\Support\OccupantPresenter;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OccupantController extends Controller
{
    public function index(OccupantAccessRequest $request, Property $property): Response
    {
        $this->authorizePropertyAccess($request, $property);

        $occupants = Occupant::query()
            ->where('organization_id', $property->organization_id)
            ->whereHas('units', fn ($query) => $query->where('units.property_id', $property->id))
            ->with(['person', 'units' => fn ($query) => $query->where('units.property_id', $property->id)])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Occupant $occupant) => OccupantPresenter::present($occupant, $property));

        return Inertia::render('organisation/occupants/index', [
            'organization' => TenancyContext::organization()->only('id', 'name', 'slug'),
            'property' => OccupantPresenter::property($property),
            'occupants' => $occupants,
            // The view sheet is on this page, so an occupant addressed by URL is
            // resolved here rather than by a page of its own. Scoped to the
            // property, so an id from another property resolves to nothing and
            // the sheet simply does not open.
            'focusedOccupant' => OccupantPresenter::focusedOccupant($request->integer('occupant') ?: null, $property),
        ]);
    }

    /**
     * The record's URL, at `/{property}/occupants/{occupant}`.
     *
     * The record itself is a sheet on the index, so this validates the id
     * exactly as a page would — delegation, organization, and that the occupant
     * actually holds a unit here — and then hands off to the query form the
     * index already reads. Keeping the check here means a URL that names
     * someone else's occupant 404s instead of redirecting to a sheet that would
     * silently fail to open.
     */
    public function show(OccupantAccessRequest $request, Property $property, Occupant $occupant): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($occupant->organization_id !== $property->organization_id, 403);

        abort_unless(
            $occupant->units()->where('units.property_id', $property->id)->exists(),
            404,
        );

        return redirect()->route('tenant.occupants.index', [
            'property' => $property,
            'mode' => 'view',
            'occupant' => $occupant->id,
        ]);
    }

    public function create(OccupantAccessRequest $request, Property $property): Response
    {
        $this->authorizePropertyAccess($request, $property);

        return Inertia::render('organisation/occupants/create', [
            'organization' => TenancyContext::organization()->only('id', 'name', 'slug'),
            'property' => OccupantPresenter::property($property),
            'units' => OccupantPresenter::units($property),
            'templates' => OccupantPresenter::agreementTemplates($property),
            'availableTokens' => LeaseAgreementTemplate::availableTokens(),
        ]);
    }

    public function store(StoreOccupantRequest $request, Property $property, OccupantService $occupantService): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        $occupant = $occupantService->create(
            $property->organization,
            $property,
            $request->user(),
            $request->validated(),
        );

        $this->syncAgreementDocument($request, $occupant, $property);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Occupant added.']);

        return redirect()->route('tenant.occupants.index', $property);
    }

    public function edit(OccupantAccessRequest $request, Property $property, Occupant $occupant, OccupantService $occupantService): Response
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($occupant->organization_id !== $property->organization_id, 403);

        return Inertia::render('organisation/occupants/edit', [
            'organization' => TenancyContext::organization()->only('id', 'name', 'slug'),
            'property' => OccupantPresenter::property($property),
            'occupant' => [
                'id' => $occupant->id,
                'first_name' => $occupant->person->first_name,
                'last_name' => $occupant->person->last_name,
                'email' => $occupant->person->email,
                'phone' => $occupant->person->phone,
                'national_id' => $occupant->person->national_id,
                'status' => $occupant->status,
                'unit_ids' => $occupantService->unitsForProperty($occupant, $property)->pluck('id')->all(),
                'lease' => OccupantPresenter::activeLease($occupant, $property),
            ],
            'units' => OccupantPresenter::units($property),
            'templates' => OccupantPresenter::agreementTemplates($property),
            'availableTokens' => LeaseAgreementTemplate::availableTokens(),
        ]);
    }

    public function update(UpdateOccupantRequest $request, Property $property, Occupant $occupant, OccupantService $occupantService): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($occupant->organization_id !== $property->organization_id, 403);

        $occupantService->update($occupant, $property, $request->user(), $request->validated());

        $this->syncAgreementDocument($request, $occupant, $property);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Occupant updated.']);

        return redirect()->route('tenant.occupants.index', $property);
    }

    public function destroy(DestroyOccupantRequest $request, Property $property, Occupant $occupant, OccupantService $occupantService): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($occupant->organization_id !== $property->organization_id, 403);

        $occupantService->softDelete($occupant);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Occupant removed.']);

        return redirect()->route('tenant.occupants.index', $property);
    }

    public function moveOut(MoveOutOccupantRequest $request, Property $property, Occupant $occupant, OccupantService $occupantService): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($occupant->organization_id !== $property->organization_id, 403);

        $occupantService->moveOut($occupant, $property, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Occupant moved out. Their rental history is preserved.']);

        return redirect()->route('tenant.occupants.index', $property);
    }

    public function agreement(OccupantAccessRequest $request, Property $property, Occupant $occupant): Response
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($occupant->organization_id !== $property->organization_id, 403);

        $lease = Lease::query()
            ->where('occupant_id', $occupant->id)
            ->where('property_id', $property->id)
            ->where('status', 'active')
            ->first();

        abort_if(! $lease, 404);

        return Inertia::render('organisation/occupants/agreement', [
            'agreementHtml' => LeaseAgreementTemplate::render((string) $lease->agreement_text, $lease),
            'documentUrl' => $lease->getFirstMedia('agreement')?->getUrl(),
            'occupantName' => trim(($occupant->person->first_name ?? '').' '.($occupant->person->last_name ?? '')),
        ]);
    }

    /**
     * Attach (or remove) the uploaded agreement document on every active
     * lease of this occupant within the property. singleFile() semantics
     * mean a fresh upload replaces any previous document.
     */
    private function syncAgreementDocument(Request $request, Occupant $occupant, Property $property): void
    {
        $leases = Lease::query()
            ->where('occupant_id', $occupant->id)
            ->where('property_id', $property->id)
            ->where('status', 'active')
            ->get();

        if ($request->boolean('lease.remove_agreement_document')) {
            $leases->each->clearMediaCollection('agreement');

            return;
        }

        if (! $request->hasFile('lease.agreement_document')) {
            return;
        }

        foreach ($leases as $lease) {
            $lease
                ->addMediaFromRequest('lease.agreement_document')
                ->toMediaCollection('agreement');
        }
    }

    /**
     * Staff may only manage occupants in properties they are delegated
     * to; owners can manage every property in the organization.
     */
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
