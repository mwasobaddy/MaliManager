<?php

namespace App\Http\Controllers;

use App\Services\PropertyAccessService;
use App\Support\TenancyContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The central landing page. When the authenticated user can access more
     * than one property across their organizations, the cross-organization
     * property picker is opened automatically so they can choose where to go.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (TenancyContext::organization()) {
            // Already inside a tenant context; nothing to pick.
            return Inertia::render('dashboard');
        }

        $properties = app(PropertyAccessService::class)->allProperties($user);

        return Inertia::render('dashboard', [
            'autoOpenPropertyPicker' => $properties->count() > 1,
        ]);
    }
}
