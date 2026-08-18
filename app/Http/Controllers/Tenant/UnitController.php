<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreUnitRequest;
use App\Models\Property;
use App\Services\PropertyService;
use App\Support\AuthLanding;
use Illuminate\Http\RedirectResponse;

class UnitController extends Controller
{
    public function store(Property $property, StoreUnitRequest $request, PropertyService $propertyService): RedirectResponse
    {
        try {
            $propertyService->addUnit(
                $property,
                $request->user(),
                $request->validated(),
            );
        } catch (\DomainException $e) {
            return back()->withErrors([
                'plan' => $e->getMessage(),
            ]);
        }

        return redirect()->to(AuthLanding::property(
            $property->organization,
            $property->slug,
            $request,
        ))->with('status', 'Unit added successfully.');
    }
}
