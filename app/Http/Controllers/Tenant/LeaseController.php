<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\EndLeaseRequest;
use App\Models\Lease;
use App\Models\Property;
use App\Services\AiRenewalService;
use App\Services\OccupantService;
use Illuminate\Http\JsonResponse;
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
        $expiring = $request->boolean('expiring');

        $leases = Lease::query()
            ->where('property_id', $property->id)
            ->when(in_array($status, ['active', 'ended'], true), fn ($query) => $query->where('status', $status))
            // Expiring = active with an end date inside the next 60 days.
            ->when($expiring, fn ($query) => $query
                ->where('status', 'active')
                ->whereNotNull('ends_at')
                ->whereBetween('ends_at', [now()->toDateString(), now()->addDays(60)->toDateString()]))
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
                'property_name' => $lease->property?->name,
                'expiring_soon' => $lease->status === 'active'
                    && $lease->ends_at !== null
                    && $lease->ends_at->between(now(), now()->addDays(60)),
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

    public function renewalSuggestion(Request $request, Property $property, Lease $lease, AiRenewalService $service): JsonResponse
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($lease->property_id !== $property->id, 403);
        abort_if($lease->status !== 'active', 422, 'Only active leases can be renewed.');

        try {
            return response()->json($service->suggest($request->user(), $property, $lease));
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }
}
