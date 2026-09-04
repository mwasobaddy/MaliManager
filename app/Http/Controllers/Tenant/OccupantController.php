<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyOccupantRequest;
use App\Http\Requests\Tenant\MoveOutOccupantRequest;
use App\Http\Requests\Tenant\StoreOccupantRequest;
use App\Http\Requests\Tenant\UpdateOccupantRequest;
use App\Models\AgreementTemplate;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Property;
use App\Services\OccupantService;
use App\Services\StaffService;
use App\Support\LeaseAgreementTemplate;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OccupantController extends Controller
{
    public function index(Request $request, Property $property): Response
    {
        $this->authorizePropertyAccess($request, $property);

        $occupants = Occupant::query()
            ->where('organization_id', $property->organization_id)
            ->whereHas('units', fn ($query) => $query->where('units.property_id', $property->id))
            ->with(['person', 'units' => fn ($query) => $query->where('units.property_id', $property->id)])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Occupant $occupant) => $this->present($occupant, $property));

        return Inertia::render('tenant/occupants/index', [
            'organization' => TenancyContext::organization()->only('id', 'name', 'slug'),
            'property' => $this->property($property),
            'occupants' => $occupants,
        ]);
    }

    public function create(Request $request, Property $property): Response
    {
        $this->authorizePropertyAccess($request, $property);

        return Inertia::render('tenant/occupants/create', [
            'organization' => TenancyContext::organization()->only('id', 'name', 'slug'),
            'property' => $this->property($property),
            'units' => $this->units($property),
            'templates' => $this->agreementTemplates($property),
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

    public function edit(Request $request, Property $property, Occupant $occupant, OccupantService $occupantService): Response
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($occupant->organization_id !== $property->organization_id, 403);

        return Inertia::render('tenant/occupants/edit', [
            'organization' => TenancyContext::organization()->only('id', 'name', 'slug'),
            'property' => $this->property($property),
            'occupant' => [
                'id' => $occupant->id,
                'first_name' => $occupant->person->first_name,
                'last_name' => $occupant->person->last_name,
                'email' => $occupant->person->email,
                'phone' => $occupant->person->phone,
                'national_id' => $occupant->person->national_id,
                'status' => $occupant->status,
                'unit_ids' => $occupantService->unitsForProperty($occupant, $property)->pluck('id')->all(),
                'lease' => $this->activeLease($occupant, $property),
            ],
            'units' => $this->units($property),
            'templates' => $this->agreementTemplates($property),
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

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function property(Property $property): array
    {
        return $property->only('id', 'name', 'slug');
    }

    /**
     * The occupant's active lease in this property together with the
     * uploaded agreement document (if any) for the edit form.
     */
    private function activeLease(Occupant $occupant, Property $property): ?array
    {
        $lease = Lease::query()
            ->where('occupant_id', $occupant->id)
            ->where('property_id', $property->id)
            ->where('status', 'active')
            ->first();

        if (! $lease) {
            return null;
        }

        $document = $lease->getFirstMedia('agreement');

        return [
            'starts_at' => $lease->starts_at?->toDateString(),
            'rent_amount' => $lease->rent_amount,
            'rent_frequency' => $lease->rent_frequency,
            'deposit' => $lease->deposit,
            'currency' => $lease->currency,
            'agreement_text' => $lease->agreement_text,
            'agreement_document_name' => $document?->file_name,
            'agreement_document_url' => $document?->getUrl(),
        ];
    }

    public function agreement(Request $request, Property $property, Occupant $occupant): Response
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($occupant->organization_id !== $property->organization_id, 403);

        $lease = Lease::query()
            ->where('occupant_id', $occupant->id)
            ->where('property_id', $property->id)
            ->where('status', 'active')
            ->first();

        abort_if(! $lease, 404);

        return Inertia::render('tenant/occupants/agreement', [
            'agreementHtml' => LeaseAgreementTemplate::render((string) $lease->agreement_text, $lease),
            'documentUrl' => $lease->getFirstMedia('agreement')?->getUrl(),
            'occupantName' => trim(($occupant->person->first_name ?? '').' '.($occupant->person->last_name ?? '')),
        ]);
    }

    /**
     * The agreement templates offered on the occupant form, grouped by
     * scope: this property's templates first, then the organization-wide
     * library.
     *
     * @return array{property: array<int, array{id: int, name: string, body_html: string}>, organization: array<int, array{id: int, name: string, body_html: string}>}
     */
    private function agreementTemplates(Property $property): array
    {
        return [
            'property' => AgreementTemplate::query()
                ->where('organization_id', $property->organization_id)
                ->where('property_id', $property->id)
                ->orderBy('name')
                ->get(['id', 'name', 'body_html'])
                ->map(fn (AgreementTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'body_html' => $template->body_html,
                ])
                ->all(),
            'organization' => AgreementTemplate::query()
                ->where('organization_id', $property->organization_id)
                ->whereNull('property_id')
                ->orderBy('name')
                ->get(['id', 'name', 'body_html'])
                ->map(fn (AgreementTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'body_html' => $template->body_html,
                ])
                ->all(),
        ];
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
     * @return array<int, array{id: int, name: string, type: string|null, status: string}>
     */
    private function units(Property $property): array
    {
        return $property->units()
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'status'])
            ->map(fn ($unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'type' => $unit->type,
                'status' => $unit->status,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Occupant $occupant, Property $property): array
    {
        return [
            'id' => $occupant->id,
            'first_name' => $occupant->person->first_name,
            'last_name' => $occupant->person->last_name,
            'email' => $occupant->person->email,
            'phone' => $occupant->person->phone,
            'national_id' => $occupant->person->national_id,
            'status' => $occupant->status,
            'units' => $occupant->units
                ->map(fn ($unit) => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'type' => $unit->type,
                    'status' => $unit->status,
                ])
                ->all(),
        ];
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
