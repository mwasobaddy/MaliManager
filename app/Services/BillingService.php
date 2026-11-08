<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\PaymentAttempt;

/**
 * Billing reads and cross-cutting collaborators. Writes (attempts,
 * payments, subscription extensions) live in app/Actions/Billing.
 */
class BillingService extends Service
{
    /**
     * The absolute tenant-domain callback URL to hand a gateway, e.g.
     * https://acme.malimanager.test/billing/mpesa/callback. Built by hand
     * because route() cannot know tenant domains.
     */
    public function callbackUrl(Organization $organization, string $routeName): string
    {
        $domain = $organization->tenant?->domains()->first()?->domain;

        abort_unless((bool) $domain, 500, 'Organization has no domain for billing callbacks.');

        $scheme = app()->runningInConsole()
            ? (string) parse_url((string) config('app.url'), PHP_URL_SCHEME)
            : request()->getScheme();

        return "{$scheme}://{$domain}".route($routeName, [], false);
    }

    /**
     * The latest still-pending attempt, so the billing page can tell the
     * user an STK push or checkout is already in flight.
     */
    public function pendingAttempt(Organization $organization): ?PaymentAttempt
    {
        return $organization->paymentAttempts()->where('status', 'pending')->latest()->first();
    }

    /**
     * Everything the billing page needs: chosen plan and price, trial and
     * subscription state, and any in-flight attempt.
     *
     * @return array<string, mixed>
     */
    public function statusFor(Organization $organization): array
    {
        $plan = $organization->plan;

        return [
            'plan' => $plan ? [
                'name' => $plan->name,
                'price' => $plan->price,
                'currency' => $plan->currency,
            ] : null,
            'on_trial' => $organization->onTrial(),
            'trial_ends_at' => $organization->trial_ends_at?->toDateString(),
            'subscribed' => $organization->hasActiveSubscription(),
            'subscription_ends_at' => $organization->subscription_ends_at?->toDateString(),
            'paid_access' => $organization->hasPaidAccess(),
            'pending_attempt' => ($attempt = $this->pendingAttempt($organization)) ? [
                'gateway' => $attempt->gateway,
                'created_at' => $attempt->created_at?->toDateTimeString(),
            ] : null,
        ];
    }
}
