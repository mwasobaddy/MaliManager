<?php

namespace App\Http\Controllers;

use App\Enums\PlatformRole;
use App\Models\Person;
use App\Models\Plan;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isOnboarded()) {
            return redirect()->route('dashboard');
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

    public function complete(Request $request, TenantService $tenantService): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->organizations()->wherePivot('is_owner', true)->first();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];

        if ($organization) {
            $rules['organization_name'] = ['required', 'string', 'max:255'];
            $rules['currency'] = ['required', 'string', 'size:3'];
            $rules['plan_slug'] = ['required', 'string', 'exists:plans,slug'];
        } else {
            $rules['account_type'] = ['required', Rule::in(['organization', 'occupant'])];

            if ($request->input('account_type') === 'organization') {
                $rules['organization_name'] = ['required', 'string', 'max:255'];
                $rules['currency'] = ['required', 'string', 'size:3'];
                $rules['plan_slug'] = ['required', 'string', 'exists:plans,slug'];
            }
        }

        $validated = $request->validate($rules);

        $user->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
        ]);

        $isOrganization = $organization !== null || ($validated['account_type'] ?? null) === 'organization';

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

            $user->assignRole(PlatformRole::Tenant->value);
        }

        $user->markOnboarded();

        return redirect()->route('dashboard');
    }
}
