<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Leases\ExportLeases;
use App\Actions\Leases\SendBulkRentReminders;
use App\Actions\Leases\SendRentReminder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\EndLeaseRequest;
use App\Http\Requests\Tenant\ExportLeasesRequest;
use App\Http\Requests\Tenant\LeaseIndexRequest;
use App\Http\Requests\Tenant\RemindBulkLeasesRequest;
use App\Http\Requests\Tenant\RemindLeaseRequest;
use App\Http\Requests\Tenant\RenewalSuggestionRequest;
use App\Models\Lease;
use App\Models\LeaseAdditionalCharge;
use App\Models\Property;
use App\Services\AiRenewalService;
use App\Services\OccupantService;
use App\Services\RentArrearsService;
use App\Services\StaffService;
use App\Support\UtilityBilling;
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

        // Overdue is computed (expected vs received), so the ids are
        // resolved first and the list constrains to them.
        $overdueIds = $overdueOnly
            ? $arrears->overdueLeaseIds($property->organization, [$property->id])
            : null;

        $leases = Lease::query()
            ->tenantIndex((int) $property->id, [
                'search' => $request->string('search')->trim()->toString(),
                'status' => $status,
                'expiring' => $expiring,
                'overdue_ids' => $overdueIds,
                'sort' => $request->string('sort')->toString(),
                'direction' => $request->string('direction')->toString(),
            ])
            ->with(['person:id,first_name,last_name,email', 'unit:id,name', 'property:id,name', 'media' => fn ($query) => $query->where('collection_name', 'agreement')])
            ->paginate(18)
            ->withQueryString();

        $balances = $arrears->balancesFor($leases->getCollection());
        $utilityTotals = UtilityBilling::totalsForLeases($leases->getCollection());

        $leases = $leases->through(fn (Lease $lease): array => $this->presentLease($lease, $balances, $utilityTotals[$lease->id] ?? null));

        return Inertia::render('organisation/leases/index', [
            'leases' => $leases,
            'focusedLease' => $request->string('mode')->toString() === 'view'
                ? $this->focusedLease($request->integer('lease'), $property, $arrears)
                : null,
            'filters' => [
                'search' => $request->string('search')->trim()->toString(),
                'status' => $status,
                'expiring' => $expiring,
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

    /**
     * Streams the CSV. `scope=page` honours the pagination the user is
     * looking at; anything else exports the whole filtered set.
     */
    public function export(ExportLeasesRequest $request, Property $property, ExportLeases $export, RentArrearsService $arrears): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizePropertyAccess($request, $property);

        $csv = $export->handle($property, $request->toData(), $arrears);

        return response()->streamDownload(
            function () use ($csv): void {
                echo $csv->content;
            },
            $csv->filename,
            ['Content-Type' => 'text/csv'],
        );
    }

    /**
     * The row shape the index table and the view sheet share. Balances ride
     * along because outstanding and overdue read from them, not the lease.
     *
     * @param  array<int, array{outstanding?: string, overdue?: bool, days_overdue?: int}>  $balances
     * @return array<string, mixed>
     */
    private function presentLease(Lease $lease, array $balances, ?array $utilityTotals = null): array
    {
        $utility = $utilityTotals ?? ['metered' => '0.00', 'fixed' => '0.00', 'one_time' => '0.00', 'shared' => '0.00', 'extras' => '0.00', 'total' => (string) ($lease->rent_amount ?? 0)];

        return [
            'id' => $lease->id,
            'occupant_id' => $lease->occupant_id,
            'occupant_name' => trim(($lease->person?->first_name ?? '').' '.($lease->person?->last_name ?? '')),
            'unit_name' => $lease->unit?->name,
            'rent_amount' => $lease->rent_amount,
            'rent_frequency' => $lease->rent_frequency,
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
            // Payable total: rent plus utilities and lease extras. The view
            // sheet breaks this down; the row only needs the headline.
            'utilities_total' => $utility['total'],
            'utility_breakdown' => null,
            'additional_charges' => [],
        ];
    }

    /**
     * Deep links (?mode=view&lease=) resolve server-side so a lease off the
     * current page still opens. Scoped to the property like the index
     * query, so a foreign id resolves to null and the sheet stays shut
     * instead of leaking a record through the URL.
     *
     * @return array<string, mixed>|null
     */
    private function focusedLease(int $id, Property $property, RentArrearsService $arrears): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $lease = Lease::query()
            ->where('property_id', $property->id)
            ->whereKey($id)
            ->with(['person:id,first_name,last_name,email', 'unit:id,name', 'property:id,name', 'media' => fn ($query) => $query->where('collection_name', 'agreement')])
            ->first();

        if (! $lease) {
            return null;
        }

        $breakdown = UtilityBilling::breakdownForLease($lease);

        $extras = LeaseAdditionalCharge::query()
            ->where('lease_id', $lease->id)
            ->orderBy('name')
            ->get()
            ->map(fn (LeaseAdditionalCharge $extra): array => [
                'id' => $extra->id,
                'name' => $extra->name,
                'kind' => $extra->kind,
                'kind_label' => $extra->label(),
                'amount' => $extra->amount,
                'currency' => $extra->currency,
                'gl_code' => $extra->gl_code,
                'is_active' => $extra->is_active,
            ])
            ->all();

        return [
            ...$this->presentLease(
                $lease,
                $arrears->balancesFor($lease->newCollection([$lease])),
                [
                    'metered' => $breakdown['metered'],
                    'fixed' => $breakdown['fixed'],
                    'one_time' => $breakdown['one_time'],
                    'shared' => $breakdown['shared'],
                    'extras' => $breakdown['extras'],
                    'total' => $breakdown['total'],
                ]
            ),
            'utility_breakdown' => $breakdown,
            'additional_charges' => $extras,
        ];
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
