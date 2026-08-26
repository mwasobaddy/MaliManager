<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\EndLeaseRequest;
use App\Models\Lease;
use App\Models\Property;
use App\Services\OccupantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Property-scoped lease management: lists every lease of the property
 * (active and ended) with links to the merged agreement, and supports
 * early termination with password confirmation.
 */
class LeaseController extends Controller
{
    public function index(Request $request, Property $property): Response
    {
        $this->authorizePropertyAccess($request, $property);

        $status = $request->string('status')->toString();

        $leases = Lease::query()
            ->where('property_id', $property->id)
            ->when(in_array($status, ['active', 'ended'], true), fn ($query) => $query->where('status', $status))
            ->with(['person:id,first_name,last_name,email', 'unit:id,name'])
            ->orderByDesc('starts_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Lease $lease): array => [
                'id' => $lease->id,
                'occupant_id' => $lease->occupant_id,
                'occupant_name' => trim(($lease->person?->first_name ?? '').' '.($lease->person?->last_name ?? '')),
                'unit_name' => $lease->unit?->name,
                'rent_amount' => $lease->rent_amount,
                'currency' => $lease->currency,
                'starts_at' => $lease->starts_at?->toDateString(),
                'ends_at' => $lease->ends_at?->toDateString(),
                'status' => $lease->status,
                'is_active' => $lease->isActive(),
                'has_agreement_text' => (bool) $lease->agreement_text,
                'has_agreement_document' => $lease->getFirstMedia('agreement') !== null,
            ]);

        return Inertia::render('tenant/leases/index', [
            'leases' => $leases,
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function end(EndLeaseRequest $request, Property $property, Lease $lease, OccupantService $occupantService): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($lease->property_id !== $property->id, 403);
        abort_if(! $lease->isActive(), 422, 'This lease has already ended.');

        $occupantService->terminateLease($lease);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lease ended.']);

        return redirect()->route('tenant.leases.index', $property);
    }

    private function authorizePropertyAccess(Request $request, Property $property): void
    {
        $user = $request->user();

        if ($user->isOwnerOf($property->organization)) {
            return;
        }

        abort(403);
    }
}
