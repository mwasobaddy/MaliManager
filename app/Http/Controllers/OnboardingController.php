<?php

namespace App\Http\Controllers;

use App\Enums\PlatformRole;
use App\Http\Requests\Auth\OnboardingCompleteRequest;
use App\Models\Person;
use App\Models\Plan;
use App\Services\TenantService;
use App\Support\AuthLanding;
use App\Support\InertiaRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
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
            'plans' => Plan::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'description', 'price', 'currency', 'properties_limit', 'units_limit'])
                ->map(fn ($plan) => [...$plan->toArray(), 'price_label' => $plan->price === 0 ? 'Free' : number_format($plan->price).' '.$plan->currency]),
        ]);
    }

    public function complete(OnboardingCompleteRequest $request, TenantService $tenantService): RedirectResponse|\Illuminate\Http\Response
    {
        $user = $request->user();
        $validated = $request->validated();
        $organization = $user->organizations()->wherePivot('is_owner', true)->first();

        $user->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
        ]);

        $isOrganization = ($validated['account_type'] ?? null) === 'organization'
            || $organization !== null;

        if ($isOrganization) {
            if ($organization) {
                $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

                $organization->update([
                    'name' => $validated['organization_name'],
                    'plan_id' => $plan->id,
                    'settings' => array_merge($organization->settings ?? [], [
                        'currency' => $validated['currency'],
                    ]),
                ]);
            } else {
                $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

                $organization = $tenantService->createOrganization(
                    owner: $user,
                    name: $validated['organization_name'],
                    plan: $plan,
                    email: $user->email,
                    phone: $validated['phone'] ?? null,
                );

                $organization->update([
                    'settings' => [
                        'currency' => $validated['currency'],
                    ],
                ]);
            }
        } else {
            $person = Person::create([
                'first_name' => $validated['name'],
                'email' => $user->email,
                'phone' => $validated['phone'] ?? null,
                'status' => 'active',
                'created_by' => $user->id,
            ]);

            $user->update([
                'person_id' => $person->id,
                'created_by' => $user->id,
            ]);

            $user->assignRole(PlatformRole::Searcher->value);
        }

        $user->markOnboarded();

        activity()->inLog('onboarding')
            ->causedBy($user)
            ->event('onboarding.completed')
            ->log('Completed onboarding');

        return InertiaRedirect::to(AuthLanding::for($user, $request), $request);
    }

    /**
     * The mandatory "add your first asset" page for fresh organizations.
     */
    public function firstAsset(Request $request): Response
    {
        $organization = $request->user()
            ->organizations()
            ->wherePivot('is_owner', true)
            ->first();

        return Inertia::render('auth/first-asset', [
            'organization' => $organization ? [
                'id' => $organization->id,
                'name' => $organization->name,
            ] : null,
        ]);
    }
}
