<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Deposits\DecideDepositRefund;
use App\Actions\Deposits\DeleteDepositDeduction;
use App\Actions\Deposits\MarkDepositRefundPaid;
use App\Actions\Deposits\RequestDepositRefund;
use App\Actions\Deposits\ReviewDepositDeductions;
use App\Actions\Deposits\StoreDepositDeduction;
use App\Actions\Deposits\SubmitDepositRefundForReview;
use App\Enums\DepositRefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ApproveDepositDeductionsRequest;
use App\Http\Requests\Tenant\DepositRefundIndexRequest;
use App\Http\Requests\Tenant\DestroyDepositDeductionRequest;
use App\Http\Requests\Tenant\MarkDepositRefundPaidRequest;
use App\Http\Requests\Tenant\ReviewDepositRefundRequest;
use App\Http\Requests\Tenant\StoreDepositDeductionRequest;
use App\Http\Requests\Tenant\StoreDepositRefundRequest;
use App\Http\Requests\Tenant\UpdateDepositRefundRequest;
use App\Http\Resources\Deposits\DepositRefundResource;
use App\Models\DepositDeduction;
use App\Models\DepositRefund;
use App\Models\Lease;
use App\Services\StaffService;
use App\Support\InertiaRedirect;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Deposit refunds awaiting a decision, and their approval history.
 */
class DepositRefundController extends Controller
{
    public function index(DepositRefundIndexRequest $request): Response
    {
        $organization = TenancyContext::organization();
        $organizationId = (int) $organization?->id;
        $status = $request->string('status')->toString();
        $search = $request->string('search')->trim()->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        // Staff only see refunds on properties they are delegated to; owners
        // see the whole organization.
        $delegatedIds = $organization !== null && ! $request->user()->isOwnerOf($organization)
            ? app(StaffService::class)->delegatedPropertyIds($request->user()->membershipFor($organization))
            : null;

        $refunds = DepositRefund::query()
            ->forOrganization($organizationId)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->whereHas(
                'lease.occupant.person',
                fn ($person) => $person
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%"),
            ))
            ->when(
                $delegatedIds !== null,
                fn ($query) => $query->whereHas(
                    'lease',
                    fn ($lease) => $lease->whereIn('property_id', $delegatedIds),
                ),
            )
            ->with([
                'lease:id,property_id,occupant_id,unit_id,deposit',
                'lease.property:id,name',
                'lease.unit:id,name',
                'lease.occupant:id,person_id',
                'lease.occupant.person:id,first_name,last_name',
                'requester:id,name',
                'decider:id,name',
                'payer:id,name',
                'reviewer:id,name',
                'deductions',
            ])
            ->when(
                $sort === 'amount',
                fn ($query) => $query->orderBy('amount_cents', $direction),
                fn ($query) => $query->orderByDesc('created_at'),
            )
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DepositRefund $refund): array => (new DepositRefundResource($refund))->resolve());

        return Inertia::render('organisation/deposit-refunds/index', [
            'refunds' => $refunds,
            'focusedRefund' => $request->string('mode')->toString() === 'view'
                ? $this->focusedRefund($organizationId, $request->integer('refund'))
                : null,
            'leases' => $this->leases($request, $organizationId),
            'filters' => ['status' => $status, 'search' => $search],
        ]);
    }

    /**
     * Deep links (?mode=view&refund=) resolve server-side so a row off the
     * current page falls back to a full record rather than a dead sheet.
     *
     * @return array<string, mixed>|null
     */
    private function focusedRefund(int $organizationId, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $refund = DepositRefund::query()
            ->forOrganization($organizationId)
            ->whereKey($id)
            ->with([
                'lease:id,property_id,occupant_id,unit_id,deposit',
                'lease.property:id,name',
                'lease.unit:id,name',
                'lease.occupant:id,person_id',
                'lease.occupant.person:id,first_name,last_name',
                'requester:id,name',
                'decider:id,name',
                'payer:id,name',
                'reviewer:id,name',
                'deductions',
            ])
            ->first();

        if (! $refund) {
            return null;
        }

        return (new DepositRefundResource($refund))->resolve();
    }

    public function store(StoreDepositRefundRequest $request, RequestDepositRefund $requestRefund): RedirectResponse
    {
        $requestRefund->handle($request->user(), $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Refund requested.']);

        return InertiaRedirect::to(route('tenant.deposit-refunds.index'), $request);
    }

    public function update(
        UpdateDepositRefundRequest $request,
        DepositRefund $refund,
        DecideDepositRefund $decide,
    ): RedirectResponse {
        $refund = $decide->handle($refund, $request->user(), $request->toData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Refund {$refund->status->value}.",
        ]);

        return InertiaRedirect::to(route('tenant.deposit-refunds.index'), $request);
    }

    public function pay(MarkDepositRefundPaidRequest $request, DepositRefund $refund, MarkDepositRefundPaid $markPaid): RedirectResponse
    {
        $refund = $markPaid->handle($refund, $request->user(), $request->toData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Refund marked as paid.',
        ]);

        return InertiaRedirect::to(route('tenant.deposit-refunds.index'), $request);
    }

    public function storeDeduction(StoreDepositDeductionRequest $request, DepositRefund $refund, StoreDepositDeduction $store): RedirectResponse
    {
        $deduction = $store->handle($refund, $request->user(), $request->validated(), $request->file('photos', []));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Deduction '{$deduction->description}' added.",
        ]);

        return back();
    }

    public function destroyDeduction(DestroyDepositDeductionRequest $request, DepositRefund $refund, DepositDeduction $deduction, DeleteDepositDeduction $delete): RedirectResponse
    {
        $delete->handle($refund, $deduction, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Deduction removed.',
        ]);

        return back();
    }

    public function submit(ReviewDepositRefundRequest $request, DepositRefund $refund, SubmitDepositRefundForReview $submit): RedirectResponse
    {
        $submit->handle($refund, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Refund submitted for review.',
        ]);

        return back();
    }

    public function review(ApproveDepositDeductionsRequest $request, DepositRefund $refund, ReviewDepositDeductions $review): RedirectResponse
    {
        $review->handle($refund, $request->user(), $request->toData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $request->input('decision') === 'approve' ? 'Deductions approved.' : 'Deductions sent back for re-inspection.',
        ]);

        return back();
    }

    /**
     * @return array<int, array{value: int, label: string, deposit: float}>
     */
    private function leases(Request $request, int $organizationId): array
    {
        $organization = TenancyContext::organization();

        $delegatedIds = $organization !== null && ! $request->user()->isOwnerOf($organization)
            ? app(StaffService::class)->delegatedPropertyIds($request->user()->membershipFor($organization))
            : null;

        return Lease::query()
            ->where('organization_id', $organizationId)
            ->where(function ($query) {
                // Active tenancies plus ended ones still holding a deposit
                // with no refund in flight: the dashboard flag and the
                // lease sheet both point here, so an ended lease must be
                // requestable instead of a dead end.
                $query->where('status', 'active')
                    ->orWhere(function ($query) {
                        $query->where('status', 'ended')
                            ->where('deposit', '>', 0)
                            ->whereNotExists(function ($query) {
                                $query->selectRaw('1')
                                    ->from('deposit_refunds')
                                    ->whereColumn('deposit_refunds.lease_id', 'leases.id')
                                    ->whereIn('deposit_refunds.status', [
                                        DepositRefundStatus::Pending->value,
                                        DepositRefundStatus::AwaitingInspection->value,
                                        DepositRefundStatus::InReview->value,
                                        DepositRefundStatus::Approved->value,
                                    ]);
                            });
                    });
            })
            ->when(
                $delegatedIds !== null,
                fn ($query) => $query->whereIn('property_id', $delegatedIds),
            )
            ->with(['occupant:id,person_id', 'occupant.person:id,first_name,last_name'])
            ->orderByDesc('id')
            ->get(['id', 'occupant_id', 'deposit'])
            ->map(fn (Lease $lease): array => [
                'value' => $lease->id,
                'label' => $lease->occupant?->person?->full_name ?: "Lease #{$lease->id}",
                'deposit' => (float) ($lease->deposit ?? 0),
            ])
            ->all();
    }
}
