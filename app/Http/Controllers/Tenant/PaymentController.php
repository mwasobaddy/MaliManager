<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyPaymentRequest;
use App\Http\Requests\Tenant\StorePaymentRequest;
use App\Http\Requests\Tenant\UpdatePaymentRequest;
use App\Models\Lease;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        $payments = Payment::query()
            ->where('organization_id', TenancyContext::organization()?->id)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->with(['lease.occupant', 'lease.property', 'lease.unit'])
            ->orderByDesc('paid_on')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Payment $payment): array => [
                'id' => $payment->id,
                'lease_id' => $payment->lease_id,
                'tenant_name' => $payment->lease?->occupant?->name,
                'property_name' => $payment->lease?->property?->name,
                'unit_name' => $payment->lease?->unit?->name,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'paid_on' => $payment->paid_on?->toDateString(),
                'method' => $payment->method,
                'status' => $payment->status,
                'reference' => $payment->reference,
            ]);

        return Inertia::render('tenant/payments/index', [
            'payments' => $payments,
            'statuses' => Payment::STATUSES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('tenant/payments/form', [
            'payment' => null,
            'leases' => $this->leases(),
        ]);
    }

    public function store(StorePaymentRequest $request, PaymentService $service): RedirectResponse
    {
        $service->create(
            $request->user(),
            TenancyContext::organization()?->id,
            $request->validated(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment recorded.']);

        return redirect()->route('tenant.payments.index');
    }

    public function edit(Payment $payment): Response
    {
        abort_if($payment->organization_id !== TenancyContext::organization()?->id, 403);

        return Inertia::render('tenant/payments/form', [
            'payment' => [
                'id' => $payment->id,
                'lease_id' => $payment->lease_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'paid_on' => $payment->paid_on?->toDateString(),
                'period_start' => $payment->period_start?->toDateString(),
                'period_end' => $payment->period_end?->toDateString(),
                'method' => $payment->method,
                'reference' => $payment->reference,
                'status' => $payment->status,
                'notes' => $payment->notes,
            ],
            'leases' => $this->leases(),
        ]);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        abort_if($payment->organization_id !== TenancyContext::organization()?->id, 403);

        $service->update($payment, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment updated.']);

        return redirect()->route('tenant.payments.index');
    }

    public function destroy(DestroyPaymentRequest $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        abort_if($payment->organization_id !== TenancyContext::organization()?->id, 403);

        $service->softDelete($payment);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment removed.']);

        return redirect()->route('tenant.payments.index');
    }

    public function show(Payment $payment): Response
    {
        abort_if($payment->organization_id !== TenancyContext::organization()?->id, 403);

        return Inertia::render('tenant/payments/receipt', [
            'tenantName' => $payment->lease?->occupant?->name ?? '—',
            'propertyName' => $payment->lease?->property?->name,
            'unitName' => $payment->lease?->unit?->name,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'paidOn' => $payment->paid_on?->toDateString(),
            'periodStart' => $payment->period_start?->toDateString(),
            'periodEnd' => $payment->period_end?->toDateString(),
            'method' => $payment->method,
            'reference' => $payment->reference,
            'status' => $payment->status,
            'notes' => $payment->notes,
        ]);
    }

    /**
     * Active leases scoped to the current organization for the payment form dropdown.
     *
     * @return list<array{id: int, name: string}>
     */
    private function leases(): array
    {
        $organizationId = TenancyContext::organization()?->id;

        return Lease::query()
            ->where('organization_id', $organizationId)
            ->with('occupant')
            ->get(['id'])
            ->map(fn (Lease $lease) => [
                'id' => $lease->id,
                'name' => $lease->occupant?->name ?? 'Lease #'.$lease->id,
            ])
            ->all();
    }
}
