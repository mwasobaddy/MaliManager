<?php

namespace App\Http\Controllers;

use App\Actions\Billing\ChoosePlan;
use App\Http\Requests\Auth\OnboardingCompleteRequest;
use App\Http\Requests\Auth\OnboardingRequest;
use App\Http\Requests\ChoosePlanRequest;
use App\Models\Plan;
use App\Services\OnboardingService;
use App\Support\AuthLanding;
use App\Support\InertiaRedirect;
use App\Support\SetupProgress;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(OnboardingRequest $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isOnboarded()) {
            return InertiaRedirect::to(AuthLanding::for($user, $request), $request);
        }

        $organization = $user->organizations()->wherePivot('is_owner', true)->first();

        return Inertia::render('auth/onboarding', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'hasOrganization' => $organization !== null,
            'organization' => $organization ? [
                'id' => $organization->id,
                'name' => $organization->name,
                'currency' => $organization->settings['currency'] ?? 'KES',
            ] : null,
        ]);
    }

    public function complete(OnboardingCompleteRequest $request, OnboardingService $service): RedirectResponse|\Illuminate\Http\Response
    {
        $user = $request->user();

        $service->complete($user, $request->validated());

        return InertiaRedirect::to(AuthLanding::for($user, $request), $request);
    }

    /**
     * The mandatory "add your first asset" page for fresh organizations.
     * Without an owned organization there is nothing to add the asset to,
     * so org-less visitors go back to the organization step instead of a
     * choice page whose links would all refuse them.
     */
    public function firstAsset(OnboardingRequest $request): Response|RedirectResponse
    {
        $organization = $request->user()
            ->organizations()
            ->wherePivot('is_owner', true)
            ->first();

        if ($organization === null) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Set up your organization first.']);

            return redirect()->route('guest.become-landlord.show');
        }

        return Inertia::render('setup/first-asset', [
            'organization' => $organization ? [
                'id' => $organization->id,
                'name' => $organization->name,
            ] : null,
            'setupProgress' => SetupProgress::forOrganization($organization),
        ]);
    }

    /**
     * Step 3, Plan & trial: confirm the plan (moved out of step 1 so the
     * choice meets the user after they have committed) with first-month
     * framing, and pick the reminder channel. Requires the organization
     * and the first asset, like the steps downstream of it.
     */
    public function plan(OnboardingRequest $request): Response|RedirectResponse
    {
        $organization = $request->user()
            ->organizations()
            ->wherePivot('is_owner', true)
            ->first();

        if ($organization === null) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Set up your organization first.']);

            return redirect()->route('guest.become-landlord.show');
        }

        if (! $organization->properties()->exists() && ! $organization->landParcels()->exists()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Add your first asset to continue setup.']);

            return redirect()->route('onboarding.first-asset');
        }

        return Inertia::render('setup/plan', [
            'organization' => $organization->only('id', 'name'),
            'plans' => Plan::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['name', 'slug', 'description', 'price', 'currency'])
                ->map(fn ($plan) => [...$plan->toArray(), 'price_label' => $plan->price === 0 ? 'Free forever' : number_format($plan->price).' '.$plan->currency]),
            'user' => [
                'phone' => $request->user()->phone,
                'notification_channel' => $request->user()->notification_channel ?? 'email',
            ],
            'setupProgress' => SetupProgress::forOrganization($organization),
        ]);
    }

    public function storePlan(ChoosePlanRequest $request, ChoosePlan $choose): RedirectResponse|\Illuminate\Http\Response
    {
        $organization = $request->user()
            ->organizations()
            ->wherePivot('is_owner', true)
            ->firstOrFail();

        $plan = Plan::where('slug', $request->string('plan_slug')->toString())->firstOrFail();

        $choose->handle(
            $organization,
            $request->user(),
            $plan,
            $request->string('notification_channel')->toString(),
            $request->string('phone')->toString() ?: null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan confirmed. Your first month is free.']);

        return redirect()->to(AuthLanding::setupUrl($organization, $request, 'setup/team'));
    }
}
