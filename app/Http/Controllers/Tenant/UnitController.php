<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Properties\UpdatePropertyUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyUnitRequest;
use App\Http\Requests\Tenant\StoreUnitRequest;
use App\Http\Requests\Tenant\UnitIndexRequest;
use App\Http\Requests\Tenant\UnitShowRequest;
use App\Http\Requests\Tenant\UpdateUnitRequest;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Unit;
use App\Services\PropertyService;
use App\Services\StaffService;
use App\Support\AuthLanding;
use App\Support\TenancyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    private const SORTABLE = [
        'name' => 'units.name',
        'rent' => 'units.monthly_rent',
        'status' => 'units.status',
        'photos' => 'units.id',
    ];

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
        $sort = $request->string('sort')->toString();
        $sortColumn = self::SORTABLE[$sort] ?? null;
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $units = Unit::query()
            ->whereHas(
                'property',
                fn (Builder $query) => $query->where(
                    'organization_id',
                    TenancyContext::organization()?->id,
                ),
            )
            ->with(['property:id,name,slug', 'media'])
            ->when(
                $request->string('search')->trim()->toString(),
                fn (Builder $query, string $search) => $query->where(
                    fn (Builder $inner) => $inner->where('units.name', 'like', "%{$search}%")
                        ->orWhere('units.type', 'like', "%{$search}%")
                        ->orWhereHas(
                            'property',
                            fn (Builder $property) => $property->where('name', 'like', "%{$search}%"),
                        ),
                ),
            )
            ->when(
                $request->string('status')->toString() !== '',
                fn (Builder $query) => $query->where('units.status', $request->string('status')->toString()),
            )
            ->when(
                $request->string('property')->toString() !== '',
                fn (Builder $query) => $query->whereHas(
                    'property',
                    fn (Builder $property) => $property->where('id', $request->integer('property')),
                ),
            )
            ->when(
                $sortColumn !== null,
                fn (Builder $query) => $query->orderBy($sortColumn, $direction),
            )
            ->when($sortColumn === null, fn (Builder $query) => $query->orderBy('units.name'))
            ->orderBy('units.id')
            ->paginate(18)
            ->withQueryString()
            ->through(fn (Unit $unit): array => [
                'id' => $unit->id,
                'name' => $unit->name,
                'slug' => $unit->slug,
                'type' => $unit->type,
                'status' => $unit->status,
                'monthly_rent' => $unit->monthly_rent,
                'deposit' => $unit->deposit,
                'photos_count' => $unit->getMedia('photos')->count(),
                // Only the first photo renders inline; the full set rides
                // along for the edit sheet, filtered in memory from the
                // already eager-loaded media. Capped at 12 per unit, so a
                // full page stays a small payload.
                'thumbnail' => $unit->getFirstMedia('photos')?->getUrl(),
                'photos' => $unit->photoPayload(),
                'property' => [
                    'id' => $unit->property?->id,
                    'name' => $unit->property?->name,
                    'slug' => $unit->property?->slug,
                ],
            ]);

        return Inertia::render('organisation/unit/index', [
            'units' => $units,
            'properties' => TenancyContext::organization()?->properties()
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
                ->all() ?? [],
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
     * One unit, with every photo.
     */
    public function show(UnitShowRequest $request, Unit $unit): Response
    {
        abort_unless(
            $unit->property?->organization_id === TenancyContext::organization()?->id,
            404,
        );

        return Inertia::render('organisation/unit/show', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'slug' => $unit->slug,
                'type' => $unit->type,
                'status' => $unit->status,
                'monthly_rent' => $unit->monthly_rent,
                'deposit' => $unit->deposit,
                'photos' => $unit->photoPayload(),
                'property' => [
                    'id' => $unit->property?->id,
                    'name' => $unit->property?->name,
                    'slug' => $unit->property?->slug,
                ],
            ],
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
