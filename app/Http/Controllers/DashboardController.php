<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcknowledgePickerRequest;
use App\Http\Requests\DashboardRequest;
use App\Services\DashboardService;
use App\Services\PropertyAccessService;
use App\Support\TenancyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Stancl\Tenancy\Database\Models\Domain;

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
    public function index(DashboardRequest $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (TenancyContext::organization()) {
            // Already inside a tenant context; nothing to pick.
            return Inertia::render('dashboard');
        }

        if ($this->isTenantHost($request)) {
            // An org subdomain serves only tenant routes: without tenancy the
            // central dashboard renders with the guest sidebar and no link to
            // the org home. Hand over to the guarded tenant overview instead.
            return redirect()->to($request->getSchemeAndHttpHost().'/overview');
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
     * Whether the request arrived on a registered organization subdomain.
     * Central routes never initialize tenancy, so on an org host the
     * dashboard would otherwise render centrally with no org context.
     */
    private function isTenantHost(DashboardRequest $request): bool
    {
        return Domain::where('domain', $request->getHost())->exists();
    }

    /**
     * Record that the user acknowledged the property picker (e.g. chose to
     * continue as admin) so it is not force-opened again this session.
     */
    public function acknowledge(AcknowledgePickerRequest $request): HttpResponse
    {
        $request->session()->put(self::ACK_KEY, true);

        return response()->noContent();
    }
}
