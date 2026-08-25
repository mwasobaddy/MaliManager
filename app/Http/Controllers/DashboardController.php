<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\PropertyAccessService;
use App\Support\TenancyContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The central landing page. When the authenticated user can access any
     * property across their organizations, the cross-organization property
     * picker is opened automatically so they can choose between staying on
     * the admin dashboard or entering a property.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (TenancyContext::organization()) {
            // Already inside a tenant context; nothing to pick.
            return Inertia::render('dashboard');
        }

        $properties = app(PropertyAccessService::class)->allProperties($user);

        return Inertia::render('dashboard', array_merge(
            app(DashboardService::class)->payload($user),
            [
                'autoOpenPropertyPicker' => $properties->isNotEmpty(),
            ],
        ));
    }
}
