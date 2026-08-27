<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\SubPermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StorePropertyRequest;
use App\Models\Organization;
use App\Models\Property;
use App\Services\PropertyService;
use App\Services\StaffService;
use App\Support\AuthLanding;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = TenancyContext::organization();

        $user = $request->user();

        $properties = $organization->properties()
            ->when(! $user->isOwnerOf($organization), fn ($query) => $query
                ->whereIn('id', app(StaffService::class)->delegatedPropertyIds($user->membershipFor($organization))))
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
            'canCreateProperty' => ($user->isOwnerOf($organization)
                || $user->hasSubPermission($organization, SubPermissionKey::PropertyCreate))
                && $this->canCreateProperty($organization),
        ]);
    }

    public function create(Request $request): Response
    {
        $organization = TenancyContext::organization();

        $this->authorizePropertyMutation($request, $organization);

        return Inertia::render('tenant/properties/create', [
            'organization' => $organization->only('id', 'name', 'slug'),
            'plan' => [
                'properties_limit' => $organization->plan?->properties_limit,
                'units_limit' => $organization->plan?->units_limit,
            ],
            'canCreateProperty' => ($request->user()->isOwnerOf($organization)
                || $request->user()->hasSubPermission($organization, SubPermissionKey::PropertyCreate))
                && $this->canCreateProperty($organization),
        ]);
    }

    public function store(StorePropertyRequest $request, PropertyService $propertyService): RedirectResponse
    {
        $organization = TenancyContext::organization();

        $this->authorizePropertyMutation($request, $organization);

        if (! $this->canCreateProperty($organization)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Your current plan does not allow adding more properties.']);

            return back();
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

    public function dashboard(Request $request, Property $property): Response
    {
        $this->authorizePropertyAccess($request, $property);

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

        return Inertia::render('tenant/properties/dashboard', [
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

    /**
     * Staff may only access properties they are delegated to; owners
     * and admins can access every property in the organization.
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

    /**
     * Only users with the property.create permission (owners or staff
     * granted it) may create organization-level structure.
     */
    private function authorizePropertyMutation(Request $request, Organization $organization): void
    {
        if (! $request->user()->isOwnerOf($organization)
            && ! $request->user()->hasSubPermission($organization, SubPermissionKey::PropertyCreate)) {
            abort(403);
        }
    }
}
