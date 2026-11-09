<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Billing\ConfirmCardPayment;
use App\Actions\Billing\InitializeCardPayment;
use App\Actions\Billing\InitiateMpesaPayment;
use App\Actions\Billing\UpdatePaymentDetails;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\InitiateCardPaymentRequest;
use App\Http\Requests\Tenant\InitiateMpesaPaymentRequest;
use App\Http\Requests\Tenant\UpdatePaymentDetailsRequest;
use App\Models\Organization;
use App\Services\BillingService;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-serve billing for the organization's plan: trial/subscription
 * status, M-Pesa STK push and card checkout. Owner-only like the setup
 * funnel — paying is an owner decision, and staff are redirected with
 * an explanation instead of a bare denial.
 */
class BillingController extends Controller
{
    public function show(BillingService $billing): Response|RedirectResponse
    {
        $organization = $this->ownedOrganization();

        if ($organization instanceof RedirectResponse) {
            return $organization;
        }

        return Inertia::render('organisation/billing/show', [
            'organization' => $organization->only('id', 'name', 'slug'),
            'billing' => $billing->statusFor($organization),
            'payment_details' => $organization->paymentDetails(),
        ]);
    }

    public function initiateMpesa(InitiateMpesaPaymentRequest $request): RedirectResponse
    {
        $organization = $this->ownedOrganization();

        if ($organization instanceof RedirectResponse) {
            return $organization;
        }

        app(InitiateMpesaPayment::class)->handle(
            $organization,
            $request->user(),
            $request->string('phone')->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Check your phone and enter your M-Pesa PIN to complete payment.']);

        return back();
    }

    public function initiateCard(InitiateCardPaymentRequest $request): RedirectResponse|\Illuminate\Http\Response
    {
        $organization = $this->ownedOrganization();

        if ($organization instanceof RedirectResponse) {
            return $organization;
        }

        $authorizationUrl = app(InitializeCardPayment::class)->handle($organization, $request->user());

        return Inertia::location($authorizationUrl);
    }

    /**
     * Paystack redirects back here with ?reference=. A tampered or failed
     * reference becomes an error toast on the billing page — never a
     * redirect back to the gateway.
     */
    public function verifyCard(Request $request): RedirectResponse
    {
        $organization = $this->ownedOrganization();

        if ($organization instanceof RedirectResponse) {
            return $organization;
        }

        try {
            app(ConfirmCardPayment::class)->handle($request->string('reference')->toString());
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return redirect()->route('tenant.billing.show');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment received. Your subscription is active.']);

        return redirect()->route('tenant.billing.show');
    }

    public function updatePaymentDetails(UpdatePaymentDetailsRequest $request): RedirectResponse
    {
        $organization = $this->ownedOrganization();

        if ($organization instanceof RedirectResponse) {
            return $organization;
        }

        app(UpdatePaymentDetails::class)->handle(
            $organization,
            $request->user(),
            $request->validated(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment details updated. Tenants see them on their rentals page.']);

        return back();
    }

    /**
     * The billing page belongs to owners. Staff are redirected with an
     * explanation instead of a bare denial.
     */
    private function ownedOrganization(): Organization|RedirectResponse
    {
        $user = request()->user();
        $organization = TenancyContext::organization();

        if ($organization === null || $user === null || ! $user->isOwnerOf($organization)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Only organization owners can do that.']);

            return redirect()->route('tenant.properties.index');
        }

        return $organization;
    }
}
