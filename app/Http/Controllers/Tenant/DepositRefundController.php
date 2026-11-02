<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Deposits\DecideDepositRefund;
use App\Actions\Deposits\RequestDepositRefund;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreDepositRefundRequest;
use App\Http\Requests\Tenant\UpdateDepositRefundRequest;
use App\Http\Resources\Deposits\DepositRefundResource;
use App\Models\DepositRefund;
use App\Models\Lease;
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
    public function index(Request $request): Response
    {
        $organizationId = (int) TenancyContext::organization()?->id;
        $status = $request->string('status')->toString();

        $refunds = DepositRefund::query()
            ->forOrganization($organizationId)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->with([
                'lease:id,occupant_id,unit_id,deposit',
                'lease.occupant:id,name',
                'requester:id,name',
                'decider:id,name',
            ])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DepositRefund $refund): array => (new DepositRefundResource($refund))->resolve());

        return Inertia::render('organisation/deposit-refunds/index', [
            'refunds' => $refunds,
            'leases' => $this->leases($organizationId),
            'filters' => ['status' => $status],
        ]);
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

    /**
     * @return array<int, array{value: int, label: string, deposit: float}>
     */
    private function leases(int $organizationId): array
    {
        return Lease::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->with('occupant:id,name')
            ->orderByDesc('id')
            ->get(['id', 'occupant_id', 'deposit'])
            ->map(fn (Lease $lease): array => [
                'value' => $lease->id,
                'label' => $lease->occupant?->name ?? "Lease #{$lease->id}",
                'deposit' => (float) ($lease->deposit ?? 0),
            ])
            ->all();
    }
}
