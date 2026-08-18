<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StorePropertyRequest;
use App\Models\Organization;
use App\Models\Property;
use App\Services\PropertyService;
use App\Support\AuthLanding;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function index(): Response
    {
        $organization = TenancyContext::organization();

        $properties = $organization->properties()
            ->withCount('units')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Property $property) => [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
                'city' => $property->city,
                'status' => $property->status,
                'units_count' => $property->units_count,
            ]);

        return Inertia::render('tenant/properties/index', [
            'organization' => $organization->only('id', 'name', 'slug'),
            'properties' => $properties,
            'canCreateProperty' => $this->canCreateProperty($organization),
        ]);
    }

    public function create(): Response
    {
        $organization = TenancyContext::organization();

        return Inertia::render('tenant/properties/create', [
            'organization' => $organization->only('id', 'name', 'slug'),
            'plan' => [
                'properties_limit' => $organization->plan?->properties_limit,
                'units_limit' => $organization->plan?->units_limit,
            ],
            'canCreateProperty' => $this->canCreateProperty($organization),
        ]);
    }

    public function store(StorePropertyRequest $request, PropertyService $propertyService): RedirectResponse
    {
        $organization = TenancyContext::organization();

        if (! $this->canCreateProperty($organization)) {
            return back()->withErrors([
                'plan' => 'Your current plan does not allow adding more properties.',
            ]);
        }

        $property = $propertyService->create(
            $organization,
            $request->user(),
            $request->validated(),
        );

        return redirect()->to(AuthLanding::property(
            $organization,
            $property->slug,
            $request,
        ))->with('status', 'Property created successfully.');
    }

    public function show(Property $property): Response
    {
        $units = $property->units()
            ->orderBy('name')
            ->get()
            ->map(fn ($unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'type' => $unit->type,
                'status' => $unit->status,
                'monthly_rent' => $unit->monthly_rent,
            ]);

        return Inertia::render('tenant/properties/show', [
            'organization' => TenancyContext::organization()->only('id', 'name', 'slug'),
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
                'address' => $property->address,
                'city' => $property->city,
                'status' => $property->status,
            ],
            'units' => $units,
            'plan' => [
                'units_limit' => $property->organization->plan?->units_limit,
            ],
        ]);
    }

    private function canCreateProperty(Organization $organization): bool
    {
        $limit = $organization->plan?->properties_limit;

        return $limit === null || $organization->properties()->count() < $limit;
    }
}
