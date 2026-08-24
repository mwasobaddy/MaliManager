<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\AuditController;
use App\Http\Controllers\Tenant\ImpersonationController;
use App\Http\Controllers\Tenant\LandParcelController;
use App\Http\Controllers\Tenant\OccupantController;
use App\Http\Controllers\Tenant\PropertyController;
use App\Http\Controllers\Tenant\StaffController;
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

        Route::middleware('sub-permission:occupant.manage')->group(function () {
            Route::get('/{property:slug}/occupants', [OccupantController::class, 'index'])
                ->name('tenant.occupants.index');
        });

        Route::middleware('sub-permission:occupant.create')->group(function () {
            Route::get('/{property:slug}/occupants/create', [OccupantController::class, 'create'])
                ->name('tenant.occupants.create');
            Route::post('/{property:slug}/occupants', [OccupantController::class, 'store'])
                ->name('tenant.occupants.store');
        });

        Route::middleware('sub-permission:occupant.edit')->group(function () {
            Route::get('/{property:slug}/occupants/{occupant}/edit', [OccupantController::class, 'edit'])
                ->name('tenant.occupants.edit');
            Route::put('/{property:slug}/occupants/{occupant}', [OccupantController::class, 'update'])
                ->name('tenant.occupants.update');

            Route::post('/{property:slug}/occupants/{occupant}/move-out', [OccupantController::class, 'moveOut'])
                ->name('tenant.occupants.move-out');
        });

        Route::middleware('sub-permission:occupant.delete')->group(function () {
            Route::delete('/{property:slug}/occupants/{occupant}', [OccupantController::class, 'destroy'])
                ->name('tenant.occupants.destroy');
        });
    });

    Route::middleware(['auth'])->group(function () {
        Route::middleware('sub-permission:land_parcel.manage')->group(function () {
            Route::get('/land-parcels', [LandParcelController::class, 'index'])
                ->name('tenant.land-parcels.index');
        });

        Route::middleware('sub-permission:land_parcel.create')->group(function () {
            Route::get('/land-parcels/create', [LandParcelController::class, 'create'])
                ->name('tenant.land-parcels.create');
            Route::post('/land-parcels', [LandParcelController::class, 'store'])
                ->name('tenant.land-parcels.store');
        });

        Route::middleware('sub-permission:land_parcel.manage')->group(function () {
            Route::get('/land-parcels/{land_parcel}', [LandParcelController::class, 'show'])
                ->name('tenant.land-parcels.show');
        });

        Route::middleware('sub-permission:land_parcel.edit')->group(function () {
            Route::get('/land-parcels/{land_parcel}/edit', [LandParcelController::class, 'edit'])
                ->name('tenant.land-parcels.edit');
            Route::put('/land-parcels/{land_parcel}', [LandParcelController::class, 'update'])
                ->name('tenant.land-parcels.update');
        });

        Route::middleware('sub-permission:land_parcel.delete')->group(function () {
            Route::delete('/land-parcels/{land_parcel}', [LandParcelController::class, 'destroy'])
                ->name('tenant.land-parcels.destroy');
        });

        Route::middleware('sub-permission:staff.manage')->group(function () {
            Route::get('/staff', [StaffController::class, 'index'])
                ->name('tenant.staff.index');
        });

        Route::middleware('sub-permission:staff.create')->group(function () {
            Route::get('/staff/create', [StaffController::class, 'create'])
                ->name('tenant.staff.create');
            Route::post('/staff', [StaffController::class, 'store'])
                ->name('tenant.staff.store');
        });

        Route::middleware('sub-permission:staff.edit')->group(function () {
            Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])
                ->name('tenant.staff.edit');
            Route::put('/staff/{staff}', [StaffController::class, 'update'])
                ->name('tenant.staff.update');
        });

        Route::middleware('sub-permission:staff.delete')->group(function () {
            Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])
                ->name('tenant.staff.destroy');
        });

        Route::middleware('sub-permission:audit.view')->group(function () {
            Route::get('/audit', [AuditController::class, 'index'])
                ->name('tenant.audit.index');
            Route::get('/audit/export', [AuditController::class, 'export'])
                ->name('tenant.audit.export');
            Route::get('/audit/{activity}', [AuditController::class, 'show'])
                ->name('tenant.audit.show');
        });
    });
});
