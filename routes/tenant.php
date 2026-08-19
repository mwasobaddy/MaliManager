<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\ImpersonationController;
use App\Http\Controllers\Tenant\PropertyController;
use App\Http\Controllers\Tenant\UnitController;
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

    Route::get('/impersonate/{token}', [ImpersonationController::class, 'login'])
        ->name('tenant.impersonate');

    Route::middleware(['auth', 'has-property'])->group(function () {
        Route::get('/properties', [PropertyController::class, 'index'])
            ->name('tenant.properties.index');
        Route::get('/properties/create', [PropertyController::class, 'create'])
            ->name('tenant.properties.create');
        Route::post('/properties', [PropertyController::class, 'store'])
            ->name('tenant.properties.store');
        Route::get('/{property:slug}/dashboard', [PropertyController::class, 'dashboard'])
            ->name('tenant.properties.dashboard');
        Route::post('/{property:slug}/units', [UnitController::class, 'store'])
            ->name('tenant.properties.units.store');
    });
});
