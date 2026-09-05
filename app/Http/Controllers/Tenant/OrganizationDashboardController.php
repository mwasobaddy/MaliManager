<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\OrganizationDashboardService;
use App\Support\TenancyContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationDashboardController extends Controller
{
    /**
     * The organization-level overview: aggregate stats plus a per-property
     * comparison for the assets the current user can access. Live on the
     * tenant subdomain, side-by-side with the org-level sidebar links.
     */
    public function index(Request $request): Response
    {
        $organization = TenancyContext::organization();
        abort_if($organization === null, 404);

        $year = $request->integer('year') ?: null;
        $month = $request->integer('month') ?: null;

        return Inertia::render('tenant/dashboard', app(OrganizationDashboardService::class)->payload(
            $request->user(),
            $organization,
            $year,
            $month,
        ));
    }
}
