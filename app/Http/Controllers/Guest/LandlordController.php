<?php

namespace App\Http\Controllers\Guest;

use App\Actions\Onboarding\BecomeLandlord;
use App\Http\Controllers\Controller;
use App\Http\Requests\BecomeLandlordRequest;
use App\Models\Plan;
use App\Support\AuthLanding;
use App\Support\InertiaRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landlord upgrade: the relocated half of the old signup branch.
 *
 * Only guests without an owned organization should arrive here; anyone who
 * already owns one is sent to their landing instead of provisioning a second
 * org by accident.
 */
class LandlordController extends Controller
{
    public function store(BecomeLandlordRequest $request, BecomeLandlord $upgrade): RedirectResponse|HttpResponse
    {
        $user = $request->user();

        if ($user->organizations()->wherePivot('is_owner', true)->exists()) {
            return InertiaRedirect::to(AuthLanding::for($user, $request), $request);
        }

        $upgrade->handle($user, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Welcome, landlord. Add your first asset to get started.']);

        return InertiaRedirect::to(AuthLanding::for($user, $request), $request);
    }

    public function show(): Response
    {
        return Inertia::render('setup/become-landlord', [
            'plans' => Plan::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['name', 'slug', 'description', 'price', 'currency'])
                ->map(fn ($plan) => [...$plan->toArray(), 'price_label' => $plan->price === 0 ? 'Free forever' : number_format($plan->price).' '.$plan->currency]),
        ]);
    }
}
