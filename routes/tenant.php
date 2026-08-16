<?php

declare(strict_types=1);

use App\Support\TenancyContext;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Real tenant routes (organization dashboard, tenant portal) are wired up
| in Phase 2 (Auth & Onboarding). Until then this file stays intentionally
| minimal so it never shadows central routes in the route collection.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Real tenant routes (organization dashboard, tenant portal) are wired up
    // in Phase 2 (Auth & Onboarding). The status route below exists to verify
    // that domain-based tenant identification and scoping work end to end.

    Route::get('/tenant/status', function () {
        return response()->json([
            'initialized' => TenancyContext::initialized(),
            'tenant_id' => TenancyContext::tenantId(),
            'organization' => TenancyContext::organization()?->only('id', 'name', 'slug'),
            'domain' => TenancyContext::domain()?->domain,
        ]);
    })->name('tenant.status');
});
