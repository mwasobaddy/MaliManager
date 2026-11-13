<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Leases\SendBulkRentReminders;
use App\Actions\Leases\SendRentReminder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\EndLeaseRequest;
use App\Http\Requests\Tenant\LeaseIndexRequest;
use App\Http\Requests\Tenant\RemindBulkLeasesRequest;
use App\Http\Requests\Tenant\RemindLeaseRequest;
use App\Http\Requests\Tenant\RenewalSuggestionRequest;
use App\Models\Lease;
use App\Models\Property;
use App\Services\AiRenewalService;
use App\Services\OccupantService;
use App\Services\RentArrearsService;
use App\Services\StaffService;
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
    public function index(LeaseIndexRequest $request, Property $property, RentArrearsService $arrears): Response
    {
        $this->authorizePropertyAccess($request, $property);

        $status = $request->string('status')->toString();
        $expiring = $request->boolean('expiring');
        $overdueOnly = $request->boolean('overdue');

        $leases = Lease::query()
            ->where('property_id', $property->id)
            ->when(in_array($status, ['active', 'ended'], true), fn ($query) => $query->where('status', $status))
            // Expiring = active with an end date inside the next 60 days.
            ->when($expiring, fn ($query) => $query
                ->where('status', 'active')
                ->whereNotNull('ends_at')
                ->whereBetween('ends_at', [now()->toDateString(), now()->addDays(60)->toDateString()]))
            // Overdue is computed (expected vs received), so the ids are
            // resolved first and the list constrains to them.
            ->when($overdueOnly, fn ($query) => $query
                ->whereIn('id', $arrears->overdueLeaseIds($property->organization, [$property->id])))
            ->with(['person:id,first_name,last_name,email', 'unit:id,name'])
            ->orderByDesc('starts_at')
            ->paginate(15)
            ->withQueryString();

        $balances = $arrears->balancesFor($leases->getCollection());

        $leases = $leases->through(fn (Lease $lease): array => [
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
            'outstanding' => $balances[$lease->id]['outstanding'] ?? '0.00',
            'is_overdue' => $balances[$lease->id]['overdue'] ?? false,
            'days_overdue' => $balances[$lease->id]['days_overdue'] ?? 0,
            'last_reminded_ago' => $lease->last_reminded_at?->diffForHumans(),
        ]);

        return Inertia::render('organisation/leases/index', [
            'leases' => $leases,
            'filters' => [
                'status' => $status,
                'overdue' => $overdueOnly,
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

    /**
     * Nudge one overdue lease. Throttle and balance errors surface as
     * toasts via the shared exception handler — no dialog needed.
     */
    public function remind(RemindLeaseRequest $request, Property $property, Lease $lease, SendRentReminder $remind): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        abort_if($lease->property_id !== $property->id, 403);

        $remind->handle($lease, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reminder sent.']);

        return back();
    }

    /**
     * Nudge several overdue leases at once. Per-lease failures (paid up
     * since, reminded within 24h) collect as skips in the summary toast
     * instead of aborting the batch.
     */
    public function remindBulk(RemindBulkLeasesRequest $request, Property $property, SendBulkRentReminders $bulk): RedirectResponse
    {
        $this->authorizePropertyAccess($request, $property);

        $result = $bulk->handle($property, $request->user(), $request->validated('lease_ids'));

        $sent = count($result['sent']);
        $skipped = count($result['skipped']);

        Inertia::flash('toast', [
            'type' => $sent > 0 ? 'success' : 'warning',
            'message' => "Sent {$sent} reminder".($sent === 1 ? '' : 's').($skipped > 0 ? ", skipped {$skipped}" : '').'.',
        ]);

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

    public function renewalSuggestion(RenewalSuggestionRequest $request, Property $property, Lease $lease, AiRenewalService $service): JsonResponse
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
