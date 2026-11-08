<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\OnboardingCompleteRequest;
use App\Http\Requests\Auth\OnboardingRequest;
use App\Services\OnboardingService;
use App\Support\AuthLanding;
use App\Support\InertiaRedirect;
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
     */
    public function firstAsset(OnboardingRequest $request): Response
    {
        $organization = $request->user()
            ->organizations()
            ->wherePivot('is_owner', true)
            ->first();

        return Inertia::render('setup/first-asset', [
            'organization' => $organization ? [
                'id' => $organization->id,
                'name' => $organization->name,
            ] : null,
        ]);
    }
}
