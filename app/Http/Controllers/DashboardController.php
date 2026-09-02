<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\PropertyAccessService;
use App\Support\TenancyContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Session key tracking whether the cross-organization property picker has
     * already been acknowledged this login. Once set, the picker is no longer
     * force-opened on every dashboard load/refresh.
     */
    private const ACK_KEY = 'property_picker_acknowledged';

    /**
     * The central landing page. When the authenticated user can access any
     * property across their organizations, the cross-organization property
     * picker is opened automatically on first arrival so they can choose
     * between staying on the admin dashboard or entering a property. Once the
     * choice is acknowledged it stays closed on subsequent loads/refreshes.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (TenancyContext::organization()) {
            // Already inside a tenant context; nothing to pick.
            return Inertia::render('dashboard');
        }

        $access = app(PropertyAccessService::class)->organizationsWithProperties($user);
        $accessibleAssets = collect($access)->flatMap(
            fn (array $organization) => [...$organization['properties'], ...$organization['land_parcels']],
        );

        $year = $request->integer('year');
        $month = $request->integer('month');

        return Inertia::render('dashboard', array_merge(
            app(DashboardService::class)->payload($user, $access, $year ?: null, $month ?: null),
            [
                'autoOpenPropertyPicker' => $accessibleAssets->isNotEmpty()
                    && ! $request->session()->get(self::ACK_KEY, false),
            ],
        ));
    }

    /**
     * Record that the user acknowledged the property picker (e.g. chose to
     * continue as admin) so it is not force-opened again this session.
     */
    public function acknowledge(Request $request): HttpResponse
    {
        $request->session()->put(self::ACK_KEY, true);

        return response()->noContent();
    }
}
