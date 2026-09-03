<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\AgreementTemplateController;
use App\Http\Controllers\Tenant\AiSettingsController;
use App\Http\Controllers\Tenant\AssistantController;
use App\Http\Controllers\Tenant\AuditController;
use App\Http\Controllers\Tenant\DraftingController;
use App\Http\Controllers\Tenant\EditorImageController;
use App\Http\Controllers\Tenant\ExpenseController;
use App\Http\Controllers\Tenant\ImpersonationController;
use App\Http\Controllers\Tenant\InspectionController;
use App\Http\Controllers\Tenant\LandParcelController;
use App\Http\Controllers\Tenant\LandParcelSectionController;
use App\Http\Controllers\Tenant\LeaseController;
use App\Http\Controllers\Tenant\MaintenanceController;
use App\Http\Controllers\Tenant\OccupantController;
use App\Http\Controllers\Tenant\PropertyController;
use App\Http\Controllers\Tenant\ReportController;
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
            ->middleware('sub-permission:property.manage')
            ->name('tenant.properties.index');
        Route::get('/properties/create', [PropertyController::class, 'create'])
            ->middleware('sub-permission:property.create')
            ->name('tenant.properties.create');
        Route::post('/properties', [PropertyController::class, 'store'])
            ->middleware('sub-permission:property.create')
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

        Route::middleware('sub-permission:inspection.manage')->group(function () {
            Route::get('/{property:slug}/inspections', [InspectionController::class, 'index'])
                ->name('tenant.inspections.index');
            Route::get('/{property:slug}/inspections/create', [InspectionController::class, 'create'])
                ->name('tenant.inspections.create');
            Route::post('/{property:slug}/inspections', [InspectionController::class, 'store'])
                ->name('tenant.inspections.store');
            Route::get('/{property:slug}/inspections/{inspection}', [InspectionController::class, 'show'])
                ->name('tenant.inspections.show');
            Route::post('/{property:slug}/inspections/{inspection}/report', [InspectionController::class, 'generateReport'])
                ->name('tenant.inspections.report');
            Route::delete('/{property:slug}/inspections/{inspection}', [InspectionController::class, 'destroy'])
                ->name('tenant.inspections.destroy');
        });

        Route::middleware('sub-permission:lease.manage')->group(function () {
            Route::get('/{property:slug}/leases', [LeaseController::class, 'index'])
                ->name('tenant.leases.index');
        });

        Route::middleware('sub-permission:lease.delete')->group(function () {
            Route::post('/{property:slug}/leases/{lease}/end', [LeaseController::class, 'end'])
                ->name('tenant.leases.end');
        });

        Route::get('/{property:slug}/leases/{lease}/renewal-suggestion', [LeaseController::class, 'renewalSuggestion'])
            ->middleware('sub-permission:lease.edit')
            ->name('tenant.leases.renewal-suggestion');

        Route::middleware('sub-permission:lease.manage_templates')->group(function () {
            // Organization reports (CSV + print-to-PDF).
            Route::get('/reports', [ReportController::class, 'index'])
                ->name('tenant.reports.index');
            Route::get('/reports/data', [ReportController::class, 'data'])
                ->name('tenant.reports.data');
            Route::get('/reports/csv', [ReportController::class, 'csv'])
                ->name('tenant.reports.csv');
            Route::get('/reports/print', [ReportController::class, 'print'])
                ->name('tenant.reports.print');

            // Ask-your-data assistant.
            Route::get('/assistant', [AssistantController::class, 'page'])
                ->middleware('sub-permission:ai.use')
                ->name('tenant.assistant.page');
            Route::post('/assistant/ask', [AssistantController::class, 'ask'])
                ->middleware('sub-permission:ai.use')
                ->name('tenant.assistant.ask');

            // Assistant conversation history.
            Route::get('/assistant/history', [AssistantController::class, 'history'])
                ->middleware('sub-permission:ai.use')
                ->name('tenant.assistant.history');
            Route::get('/assistant/conversations/{id}', [AssistantController::class, 'showConversation'])
                ->middleware('sub-permission:ai.use')
                ->name('tenant.assistant.conversation');
            Route::delete('/assistant/conversations/{id}', [AssistantController::class, 'destroyConversation'])
                ->middleware('sub-permission:ai.use')
                ->name('tenant.assistant.conversation.destroy');

            // Content drafting studio.
            Route::get('/drafting', [DraftingController::class, 'page'])
                ->name('tenant.drafting.page');
            Route::post('/drafting/generate', [DraftingController::class, 'generate'])
                ->name('tenant.drafting.generate');

            // Organization AI settings (owner-only).
            Route::get('/ai-settings', [AiSettingsController::class, 'edit'])
                ->name('tenant.ai-settings.edit');
            Route::put('/ai-settings', [AiSettingsController::class, 'update'])
                ->name('tenant.ai-settings.update');
            Route::delete('/ai-settings/key', [AiSettingsController::class, 'destroyKey'])
                ->name('tenant.ai-settings.destroy');

            // Organization-level expense records (target properties or land parcels).
            Route::get('/expenses', [ExpenseController::class, 'index'])
                ->middleware('sub-permission:expense.manage')
                ->name('tenant.expenses.index');
            Route::get('/expenses/create', [ExpenseController::class, 'create'])
                ->middleware('sub-permission:expense.manage')
                ->name('tenant.expenses.create');
            Route::post('/expenses', [ExpenseController::class, 'store'])
                ->middleware('sub-permission:expense.manage')
                ->name('tenant.expenses.store');
            Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])
                ->middleware('sub-permission:expense.manage')
                ->name('tenant.expenses.edit');
            Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])
                ->middleware('sub-permission:expense.manage')
                ->name('tenant.expenses.update');
            Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])
                ->middleware('sub-permission:expense.manage')
                ->name('tenant.expenses.destroy');

            // Maintenance request workflow (staff side).
            Route::get('/maintenance', [MaintenanceController::class, 'index'])
                ->middleware('sub-permission:maintenance.manage')
                ->name('tenant.maintenance.index');
            Route::put('/maintenance/{record}', [MaintenanceController::class, 'update'])
                ->middleware('sub-permission:maintenance.edit')
                ->name('tenant.maintenance.update');
            Route::delete('/maintenance/{record}', [MaintenanceController::class, 'destroy'])
                ->middleware('sub-permission:maintenance.delete')
                ->name('tenant.maintenance.destroy');

            // Organization-wide templates.
            Route::get('/agreement-templates', [AgreementTemplateController::class, 'orgIndex'])
                ->name('tenant.agreement-templates.org-index');
            Route::get('/agreement-templates/create', [AgreementTemplateController::class, 'create'])
                ->name('tenant.agreement-templates.create');
            Route::post('/agreement-templates', [AgreementTemplateController::class, 'store'])
                ->name('tenant.agreement-templates.store');
            Route::get('/agreement-templates/{template}/edit', [AgreementTemplateController::class, 'edit'])
                ->name('tenant.agreement-templates.edit');
            Route::put('/agreement-templates/{template}', [AgreementTemplateController::class, 'update'])
                ->name('tenant.agreement-templates.update');
            Route::delete('/agreement-templates/{template}', [AgreementTemplateController::class, 'destroy'])
                ->name('tenant.agreement-templates.destroy');

            // Property-scoped templates.
            Route::get('/{property:slug}/agreement-templates/manage', [AgreementTemplateController::class, 'propertyIndex'])
                ->name('tenant.agreement-templates.property-index');
            Route::get('/{property:slug}/agreement-templates/manage/create', [AgreementTemplateController::class, 'createForProperty'])
                ->name('tenant.agreement-templates.property-create');
            Route::post('/{property:slug}/agreement-templates/manage', [AgreementTemplateController::class, 'storeForProperty'])
                ->name('tenant.agreement-templates.property-store');
            Route::get('/{property:slug}/agreement-templates/manage/{template}/edit', [AgreementTemplateController::class, 'editForProperty'])
                ->name('tenant.agreement-templates.property-edit');
            Route::put('/{property:slug}/agreement-templates/manage/{template}', [AgreementTemplateController::class, 'updateForProperty'])
                ->name('tenant.agreement-templates.property-update');
            Route::delete('/{property:slug}/agreement-templates/manage/{template}', [AgreementTemplateController::class, 'destroyForProperty'])
                ->name('tenant.agreement-templates.property-destroy');

            // Grouped picker data for the occupant form.
            Route::get('/{property:slug}/agreement-templates/picker', [AgreementTemplateController::class, 'picker'])
                ->withoutMiddleware('sub-permission:lease.manage_templates')
                ->middleware('sub-permission:occupant.edit')
                ->name('tenant.agreement-templates.picker.index');
        });

        Route::middleware('sub-permission:occupant.edit')->group(function () {
            Route::get('/{property:slug}/occupants/{occupant}/edit', [OccupantController::class, 'edit'])
                ->name('tenant.occupants.edit');
            Route::get('/{property:slug}/occupants/{occupant}/agreement', [OccupantController::class, 'agreement'])
                ->name('tenant.occupants.agreement');
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

        // Land parcel sections: sub-plots that can be leased independently.
        Route::middleware('sub-permission:land_parcel.manage')->group(function () {
            Route::post('/land-parcels/{land_parcel}/sections', [LandParcelSectionController::class, 'store'])
                ->name('tenant.land-parcel-sections.store');
            Route::delete('/land-parcels/{land_parcel}/sections/{land_parcel_section}', [LandParcelSectionController::class, 'destroy'])
                ->name('tenant.land-parcel-sections.destroy');
        });

        Route::middleware('sub-permission:lease.manage')->group(function () {
            Route::post('/land-parcels/{land_parcel}/sections/{land_parcel_section}/leases', [LandParcelSectionController::class, 'lease'])
                ->name('tenant.land-parcel-sections.lease');
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

        // Rich-text editor image upload (agreement templates + lease agreements).
        Route::post('/editor-images', [EditorImageController::class, 'store'])
            ->name('tenant.editor-images.store');
    });
});
