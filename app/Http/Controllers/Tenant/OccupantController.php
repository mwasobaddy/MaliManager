<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyOccupantRequest;
use App\Http\Requests\Tenant\StoreOccupantRequest;
use App\Http\Requests\Tenant\UpdateOccupantRequest;
use App\Models\Occupant;
use App\Models\Property;
use App\Services\OccupantService;
use App\Services\StaffService;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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
        ]);
    }

    public function store(StoreOccupantRequest $request, Property $property, OccupantService $occupantService): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        try {
            $occupantService->create(
                $property->organization,
                $property,
                $request->user(),
                $request->validated(),
            );
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'We could not add this occupant. Please try again.']);

            return back();
        }

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
            ],
            'units' => $this->units($property),
        ]);
    }

    public function update(UpdateOccupantRequest $request, Property $property, Occupant $occupant, OccupantService $occupantService): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($occupant->organization_id !== $property->organization_id, 403);

        try {
            $occupantService->update($occupant, $property, $request->user(), $request->validated());
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'We could not update this occupant. Please try again.']);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Occupant updated.']);

        return redirect()->route('tenant.occupants.index', $property);
    }

    public function destroy(DestroyOccupantRequest $request, Property $property, Occupant $occupant, OccupantService $occupantService): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($occupant->organization_id !== $property->organization_id, 403);

        try {
            $occupantService->softDelete($occupant);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'We could not delete this occupant. Please try again.']);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Occupant removed.']);

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
