<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Occupants\ImportOccupants;
use App\Concerns\FlashesImportResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyOccupantRequest;
use App\Http\Requests\Tenant\ImportOccupantsRequest;
use App\Http\Requests\Tenant\MoveInOccupantRequest;
use App\Http\Requests\Tenant\MoveOutOccupantRequest;
use App\Http\Requests\Tenant\OccupantAccessRequest;
use App\Http\Requests\Tenant\OccupantIndexRequest;
use App\Http\Requests\Tenant\StoreOccupantRequest;
use App\Http\Requests\Tenant\UpdateOccupantRequest;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Property;
use App\Services\OccupantService;
use App\Services\RentArrearsService;
use App\Services\StaffService;
use App\Support\DeletionTier;
use App\Support\LeaseAgreementTemplate;
use App\Support\OccupantPresenter;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class OccupantController extends Controller
{
    use FlashesImportResult;

    public function index(OccupantIndexRequest $request, Property $property, RentArrearsService $arrears): Response
    {
        $this->authorizePropertyAccess($request, $property);

        $actor = $request->user();
        $organization = $property->organization;

        $paginator = Occupant::query()
            ->tenantIndex((int) $property->organization_id, (int) $property->id, [
                'search' => $request->string('search')->trim()->toString(),
                'status' => $request->string('status')->toString(),
                'sort' => $request->string('sort')->toString(),
                'direction' => $request->string('direction')->toString(),
            ])
            ->with(['person', 'units' => fn ($query) => $query->where('units.property_id', $property->id)])
            ->withExists(['leases as has_agreement' => fn ($query) => $query
                ->where('leases.property_id', $property->id)
                ->where(fn ($inner) => $inner
                    ->whereNotNull('leases.agreement_text')
                    ->whereNotIn('leases.agreement_text', ['', '<p></p>'])
                    ->orWhereHas('media', fn ($media) => $media->where('collection_name', 'agreement'))),
            ])
            ->paginate(18)
            ->withQueryString();

        // The row's one-tap action needs its tenancy: which single lease is
        // overdue and for how much. Computed once for the page, not per row.
        $attention = $this->attentionMap($property, $paginator->getCollection(), $arrears);

        $occupants = $paginator->through(fn (Occupant $occupant) => OccupantPresenter::present($occupant, $property, $actor, $organization, $attention[$occupant->id] ?? null));

        return Inertia::render('organisation/occupants/index', [
            'organization' => TenancyContext::organization()->only('id', 'name', 'slug'),
            'property' => OccupantPresenter::property($property),
            'occupants' => $occupants,
            // Quick add defaults the lease to this currency.
            'currency' => $property->organization->settings['currency'] ?? 'KES',
            // The view sheet is on this page, so an occupant addressed by URL is
            // resolved here rather than by a page of its own. Scoped to the
            // property, so an id from another property resolves to nothing and
            // the sheet simply does not open.
            'focusedOccupant' => OccupantPresenter::focusedOccupant($request->integer('occupant') ?: null, $property, $actor, $organization),
            // Create and edit are overlays on this page too; the unit list,
            // template library and tokens only load when a sheet opens.
            'units' => Inertia::optional(fn () => OccupantPresenter::units($property)),
            'templates' => Inertia::optional(fn () => OccupantPresenter::agreementTemplates($property)),
            'availableTokens' => Inertia::optional(fn () => LeaseAgreementTemplate::availableTokens()),
        ]);
    }

    /**
     * The row's one-tap action, keyed by occupant id: when exactly one
     * active lease is overdue, its id and balance ride along so the row
     * can remind without a second request. Zero or several overdue leases
     * offer nothing — the sheet (which can pick a tenancy) takes over.
     *
     * @return array<int, array{lease_id: int, outstanding: string, currency: string|null}>
     */
    private function attentionMap(Property $property, Collection $occupants, RentArrearsService $arrears): array
    {
        if ($occupants->isEmpty()) {
            return [];
        }

        $leases = Lease::query()
            ->whereIn('occupant_id', $occupants->modelKeys())
            ->where('property_id', $property->id)
            ->where('status', 'active')
            ->get();

        if ($leases->isEmpty()) {
            return [];
        }

        $balances = $arrears->balancesFor($leases);

        $overdueByOccupant = [];
        foreach ($leases as $lease) {
            if (($balances[$lease->id]['overdue'] ?? false) === true) {
                $overdueByOccupant[$lease->occupant_id][] = $lease;
            }
        }

        $attention = [];
        foreach ($overdueByOccupant as $occupantId => $due) {
            if (count($due) === 1) {
                $attention[$occupantId] = [
                    'lease_id' => $due[0]->id,
                    'outstanding' => $balances[$due[0]->id]['outstanding'],
                    'currency' => $due[0]->currency,
                ];
            }
        }

        return $attention;
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

    public function create(Property $property): RedirectResponse
    {
        return redirect()->route('tenant.occupants.index', [
            'property' => $property->slug,
            'create' => 1,
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

    /**
     * Bulk-add tenants to this property from a CSV. Bad rows are reported
     * per row and the sheet stays open on them.
     */
    public function import(ImportOccupantsRequest $request, Property $property, ImportOccupants $import): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        $result = $import->handle($request->toData($property));

        return $this->respondToImport($result, 'occupant', 'occupants', 'tenant.occupants.index');
    }

    public function edit(OccupantAccessRequest $request, Property $property, Occupant $occupant): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($occupant->organization_id !== $property->organization_id, 403);

        return redirect()->route('tenant.occupants.index', [
            'property' => $property->slug,
            'mode' => 'edit',
            'occupant' => $occupant->id,
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

        abort_unless(DeletionTier::allows($request->user(), $property->organization, $occupant), 403);

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

    public function moveIn(MoveInOccupantRequest $request, Property $property, Occupant $occupant, OccupantService $occupantService): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($occupant->organization_id !== $property->organization_id, 403);

        $occupantService->moveIn($occupant, $property, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Occupant moved in. Deposit and first rent recorded.']);

        return redirect()->route('tenant.occupants.index', $property);
    }

    public function agreement(OccupantAccessRequest $request, Property $property, Occupant $occupant): Response
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($occupant->organization_id !== $property->organization_id, 403);

        // Ended tenancies keep printable history: resolve through the same
        // rule as the view sheet's button, so the link never 404s on an
        // empty current lease while an older one prints.
        $lease = OccupantPresenter::resolveAgreementLease($occupant, $property);

        abort_if(! $lease, 404);

        $documentUrl = $lease->getFirstMedia('agreement')?->getUrl();

        return Inertia::render('organisation/occupants/agreement', [
            'agreementHtml' => LeaseAgreementTemplate::render((string) $lease->agreement_text, $lease),
            'documentUrl' => $documentUrl,
            'occupantName' => trim(($occupant->person->first_name ?? '').' '.($occupant->person->last_name ?? '')),
            'propertySlug' => $property->slug,
            'occupantId' => $occupant->id,
            'terms' => [
                'landlord' => $lease->property?->organization?->name,
                'property' => $lease->property?->name,
                'unit' => $lease->unit?->name,
                'rent_amount' => $lease->rent_amount,
                'rent_frequency' => $lease->rent_frequency,
                'currency' => $lease->currency,
                'deposit' => $lease->deposit,
                'starts_at' => $lease->starts_at?->toDateString(),
                'ends_at' => $lease->ends_at?->toDateString(),
                'printed_at' => now()->toDateString(),
            ],
        ]);
    }

    /**
     * Attach (or remove) the uploaded agreement document on every active
     * lease of this person within the property. One occupant may now hold
     * several rows, so this is scoped by person — not by the row the
     * upload came through — or sister tenancies would miss the document.
     * singleFile() semantics mean a fresh upload replaces any previous
     * document.
     */
    private function syncAgreementDocument(Request $request, Occupant $occupant, Property $property): void
    {
        $leases = Lease::query()
            ->where('person_id', $occupant->person_id)
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
