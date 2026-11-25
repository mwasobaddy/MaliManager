<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Properties\ExportUnits;
use App\Actions\Properties\ImportUnits;
use App\Actions\Properties\UpdatePropertyUnit;
use App\Concerns\FlashesImportResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyUnitRequest;
use App\Http\Requests\Tenant\ExportUnitsRequest;
use App\Http\Requests\Tenant\ImportUnitsRequest;
use App\Http\Requests\Tenant\StoreUnitRequest;
use App\Http\Requests\Tenant\UnitIndexRequest;
use App\Http\Requests\Tenant\UnitShowRequest;
use App\Http\Requests\Tenant\UpdateUnitRequest;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityCharge;
use App\Services\PropertyService;
use App\Services\StaffService;
use App\Support\AuthLanding;
use App\Support\DeletionTier;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    use FlashesImportResult;

    /**
     * Every unit in the organization, across properties.
     *
     * Units have never had a list of their own — they only existed as rows
     * inside a property, with photos stored against them but nowhere to see
     * them. This is the place a landlord can browse the whole portfolio and
     * open one to look at its pictures.
     */
    public function index(UnitIndexRequest $request): Response
    {
        $actor = $request->user();
        $organization = TenancyContext::organization();

        $units = Unit::query()
            ->tenantIndex((int) TenancyContext::organization()?->id, [
                'search' => $request->string('search')->trim()->toString(),
                'status' => $request->string('status')->toString(),
                'property' => $request->string('property')->toString(),
                'sort' => $request->string('sort')->toString(),
                'direction' => $request->string('direction')->toString(),
            ])
            ->with(['property:id,name,slug', 'media'])
            ->paginate(18)
            ->withQueryString()
            ->through(fn (Unit $unit): array => $this->presentUnit($unit, $actor, $organization));

        $properties = TenancyContext::organization()?->properties()
            ->orderBy('name')
            ->withCount('units')
            ->with('units:id,property_id,name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Property $property): array => [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
                'units_count' => $property->units_count ?? 0,
                // Names for the duplicate check on blur; ids alone
                // cannot tell "A1" from "a1" without another trip.
                'unit_names' => $property->units->pluck('name')->all(),
            ])
            ->all() ?? [];

        return Inertia::render('organisation/unit/index', [
            'units' => $units,
            'focusedUnit' => $request->string('mode')->toString() === 'view'
                ? $this->focusedUnit($request->integer('unit'), $actor, $organization)
                : null,
            'properties' => $properties,
            // Active charges per property for the create sheet's optional
            // assignment section: one query, only what the sheet renders.
            'propertyCharges' => UtilityCharge::query()
                ->where('organization_id', TenancyContext::organization()?->id)
                ->whereIn('property_id', array_column($properties, 'id'))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'property_id', 'name', 'kind'])
                ->map(fn (UtilityCharge $charge): array => [
                    'id' => $charge->id,
                    'property_id' => $charge->property_id,
                    'name' => $charge->name,
                    'kind' => $charge->kind->value,
                ])
                ->all(),
            'plan' => [
                'units_limit' => TenancyContext::organization()?->effectivePlan()?->units_limit,
            ],
            // Units that already hold photos, for the reuse picker.
            'photoLibrary' => ($organization = TenancyContext::organization()) !== null
                ? app(PropertyService::class)->photoLibrary($organization)
                : [],
            // Unit types come from the platform property-type catalogue so
            // every tenant picks from the same admin-curated list.
            'unitTypes' => PropertyType::query()
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name')
                ->all(),
        ]);
    }

    /**
     * Streams the CSV. `scope=page` honours the pagination the user is looking
     * at; anything else exports the whole filtered set.
     */
    public function export(ExportUnitsRequest $request, ExportUnits $export): \Symfony\Component\HttpFoundation\Response
    {
        $csv = $export->handle($request->toData());

        return response()->streamDownload(
            function () use ($csv): void {
                echo $csv->content;
            },
            $csv->filename,
            ['Content-Type' => 'text/csv'],
        );
    }

    /**
     * Bulk upsert shaped like the export. The sheet stays open with the
     * per-row report when anything fails.
     */
    public function import(ImportUnitsRequest $request, ImportUnits $import): RedirectResponse
    {
        $result = $import->handle($request->toData());

        return $this->respondToImport($result, 'unit', 'units', 'tenant.units.index');
    }

    /**
     * The row shape the index table and the view sheet share: scalars, the
     * cover-first thumbnail, every photo, and the server-computed delete
     * tier. The view sheet falls back to the visible rows, so the shapes
     * must stay identical.
     *
     * @return array<string, mixed>
     */
    private function presentUnit(Unit $unit, User $actor, ?Organization $organization): array
    {
        return [
            'id' => $unit->id,
            'name' => $unit->name,
            'slug' => $unit->slug,
            'type' => $unit->type,
            'status' => $unit->status,
            'rent_amount' => $unit->rent_amount,
            'rent_frequency' => $unit->rent_frequency,
            'deposit' => $unit->deposit,
            'photos_count' => $unit->getMedia('photos')->count(),
            // Only the first photo renders inline; the full set rides
            // along for the edit sheet, filtered in memory from the
            // already eager-loaded media. Capped at 12 per unit, so a
            // full page stays a small payload.
            'thumbnail' => ($unit->getMedia('photos')->first(
                fn ($media): bool => (bool) $media->getCustomProperty('cover', false),
            ) ?? $unit->getFirstMedia('photos'))?->getUrl(),
            'photos' => $unit->photoPayload(),
            'property' => [
                'id' => $unit->property?->id,
                'name' => $unit->property?->name,
                'slug' => $unit->property?->slug,
            ],
            'deletable' => DeletionTier::allows($actor, $organization, $unit),
        ];
    }

    /**
     * Deep links (?mode=view&unit=) resolve server-side so a unit off the
     * current page still opens. Scoped to the organization like the index
     * query, so a foreign id resolves to null and the sheet stays shut
     * instead of leaking a record through the URL.
     *
     * @return array<string, mixed>|null
     */
    private function focusedUnit(int $id, User $actor, ?Organization $organization): ?array
    {
        if ($id <= 0 || $organization === null) {
            return null;
        }

        $unit = Unit::query()
            ->whereKey($id)
            ->whereHas('property', fn ($query) => $query->where('organization_id', $organization->id))
            ->with(['property:id,name,slug', 'media'])
            ->first();

        if (! $unit) {
            return null;
        }

        return $this->presentUnit($unit, $actor, $organization);
    }

    /**
     * The record is a sheet on the index, not a page of its own. This URL is
     * kept for links and bookmarks; the controller validates the id and
     * redirects to the query form the page already reads.
     */
    public function show(UnitShowRequest $request, Unit $unit): RedirectResponse
    {
        abort_unless(
            $unit->property?->organization_id === TenancyContext::organization()?->id,
            404,
        );

        return redirect()->route('tenant.units.index', [
            'mode' => 'view',
            'unit' => $unit->id,
        ]);
    }

    public function store(Property $property, StoreUnitRequest $request, PropertyService $propertyService): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        $propertyService->addUnit(
            $property,
            $request->user(),
            $request->validated(),
        );

        // Back keeps both entry points in place: the dashboard inline form
        // lands back on the dashboard, the units index sheet lands back on
        // the index (so its onSuccess only has to close the sheet). Direct
        // posts without a referer fall through to the property dashboard.
        $redirect = redirect()->back(fallback: AuthLanding::property(
            $property->organization,
            $property->slug,
            $request,
        ))->with('status', 'Unit added successfully.');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit added successfully.']);

        return $redirect;
    }

    /**
     * Scalars, an optional move, and appended photos — handled by the action.
     */
    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        abort_unless(
            $unit->property?->organization_id === TenancyContext::organization()?->id,
            404,
        );

        app(UpdatePropertyUnit::class)->handle($unit, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit updated.']);

        return back();
    }

    /**
     * An occupied unit keeps its history: end the lease first instead of
     * deleting the rooms out from under an active tenancy.
     */
    public function destroy(DestroyUnitRequest $request, Unit $unit): RedirectResponse
    {
        abort_unless(
            $unit->property?->organization_id === TenancyContext::organization()?->id,
            404,
        );

        abort_unless(DeletionTier::allows($request->user(), TenancyContext::organization(), $unit), 403);

        $occupied = Lease::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'active')
            ->exists();

        abort_if($occupied, 422, 'An occupied unit cannot be deleted. End its lease first.');

        $unit->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit deleted.']);

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
