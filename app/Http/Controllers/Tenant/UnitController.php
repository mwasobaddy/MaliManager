<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreUnitRequest;
use App\Models\Property;
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
    public function index(Request $request): Response
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
                // Only the first photo inline; the rest are behind the sheet so
                // a page of 18 units does not ship 200 image URLs.
                'thumbnail' => $unit->getFirstMedia('photos')?->getUrl(),
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
                ->get(['id', 'name'])
                ->map(fn (Property $property): array => [
                    'id' => $property->id,
                    'name' => $property->name,
                ])
                ->all() ?? [],
        ]);
    }

    /**
     * One unit, with every photo.
     */
    public function show(Request $request, Unit $unit): Response
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

        return redirect()->to(AuthLanding::property(
            $property->organization,
            $property->slug,
            $request,
        ))->with('status', 'Unit added successfully.');
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
