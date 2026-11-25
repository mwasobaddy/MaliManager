<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\AgreementTemplateController;
use App\Http\Controllers\Tenant\AiSettingsController;
use App\Http\Controllers\Tenant\AssistantController;
use App\Http\Controllers\Tenant\AuditController;
use App\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Tenant\DepositRefundController;
use App\Http\Controllers\Tenant\DraftingController;
use App\Http\Controllers\Tenant\EditorImageController;
use App\Http\Controllers\Tenant\ExpenseController;
use App\Http\Controllers\Tenant\ImpersonationController;
use App\Http\Controllers\Tenant\InspectionController;
use App\Http\Controllers\Tenant\LandParcelController;
use App\Http\Controllers\Tenant\LandParcelSectionController;
use App\Http\Controllers\Tenant\LeaseAdditionalChargeController;
use App\Http\Controllers\Tenant\LeaseController;
use App\Http\Controllers\Tenant\MaintenanceController;
use App\Http\Controllers\Tenant\MaintenanceNoteController;
use App\Http\Controllers\Tenant\MpesaCallbackController;
use App\Http\Controllers\Tenant\OccupantController;
use App\Http\Controllers\Tenant\OrganizationDashboardController;
use App\Http\Controllers\Tenant\PaymentController;
use App\Http\Controllers\Tenant\PropertyController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\SetupController;
use App\Http\Controllers\Tenant\StaffController;
use App\Http\Controllers\Tenant\SubRoleController;
use App\Http\Controllers\Tenant\TrashController;
use App\Http\Controllers\Tenant\UnitController;
use App\Http\Controllers\Tenant\UtilityChargeController;
use App\Http\Controllers\Tenant\UtilityReadingController;
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

    // Funnel steps 3 and 4 live outside the first-property gate: inviting the
    // team and reviewing the setup happen before or right after the first
    // asset exists. They are owner-only; SetupController redirects staff.
    Route::middleware(['auth', 'organization.active'])->group(function () {
        Route::get('/setup/team', [SetupController::class, 'team'])
            ->name('tenant.setup.team');
        Route::post('/setup/team', [SetupController::class, 'storeTeam'])
            ->name('tenant.setup.team.store');
        Route::get('/setup/review', [SetupController::class, 'review'])
            ->name('tenant.setup.review');

        // Self-serve billing for the organization's plan. Owner-only;
        // BillingController redirects staff. Kept outside has-property so
        // a lapsed organization can always reach the page that unblocks
        // it. The M-Pesa callback carries no session, so it sits outside
        // auth (and is CSRF-exempt in bootstrap/app.php).
        Route::get('/billing', [BillingController::class, 'show'])
            ->name('tenant.billing.show');
        Route::put('/billing/payment-details', [BillingController::class, 'updatePaymentDetails'])
            ->name('tenant.billing.payment-details');
        Route::post('/billing/mpesa', [BillingController::class, 'initiateMpesa'])
            ->name('tenant.billing.mpesa');
        Route::post('/billing/card', [BillingController::class, 'initiateCard'])
            ->name('tenant.billing.card');
        Route::get('/billing/card/callback', [BillingController::class, 'verifyCard'])
            ->name('tenant.billing.card.callback');
        Route::post('/billing/mpesa/callback', [MpesaCallbackController::class, 'store'])
            ->withoutMiddleware(['auth', 'organization.active'])
            ->name('tenant.billing.mpesa.callback');
    });

    Route::middleware(['auth', 'organization.active', 'has-property'])->group(function () {
        Route::get('/overview', [OrganizationDashboardController::class, 'index'])
            ->name('tenant.overview');

        // Organization-wide, because a refund is a decision about money held,
        // not a property-level listing.
        Route::middleware('sub-permission:lease.manage')->group(function () {
            Route::get('/deposit-refunds', [DepositRefundController::class, 'index'])
                ->name('tenant.deposit-refunds.index');
            Route::post('/deposit-refunds', [DepositRefundController::class, 'store'])
                ->name('tenant.deposit-refunds.store');
        });

        Route::patch('/deposit-refunds/{refund}', [DepositRefundController::class, 'update'])
            ->middleware('sub-permission:lease.edit')
            ->name('tenant.deposit-refunds.update');
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

        // Units are browsable across the whole organization, not only from
        // inside a property — photos are stored per unit and until now there
        // was nowhere to actually see them. Kept inside `has-property`, because
        // a unit only exists under a property.
        Route::middleware('sub-permission:unit.manage')->group(function () {
            Route::get('/units', [UnitController::class, 'index'])
                ->name('tenant.units.index');
            // Before the show route: otherwise `export` binds as {unit}.
            Route::get('/units/export', [UnitController::class, 'export'])
                ->name('tenant.units.export');
            Route::post('/units/import', [UnitController::class, 'import'])
                ->middleware(['sub-permission:unit.create', 'sub-permission:unit.edit'])
                ->name('tenant.units.import');
            // The record is a sheet on the index, not a page of its own. This
            // URL is kept for links and bookmarks; the controller validates
            // the id and redirects to the query form the page already reads.
            //
            // `whereNumber` is load-bearing: without it an unconstrained
            // `{unit}` would swallow future string segments under /units.
            Route::get('/units/{unit}', [UnitController::class, 'show'])
                ->whereNumber('unit')
                ->name('tenant.units.show');

            // Utility meters on a unit. Reads are org-wide because a
            // caretaker walks a route across properties, not one unit at a time.
            Route::get('/utilities', [UtilityReadingController::class, 'index'])
                ->name('tenant.utilities.index');
            // Before the /{reading} routes: there are none today, but a
            // bare segment would swallow `export` if one is ever added.
            Route::get('/utilities/export', [UtilityReadingController::class, 'export'])
                ->name('tenant.utilities.export');
            Route::post('/utilities', [UtilityReadingController::class, 'store'])
                ->name('tenant.utilities.store');
            // One row per walked meter: the grid posts only filled rows, one
            // shared date. Same gate as single readings, so a caretaker with
            // unit.manage records the route while rates stay landlord-set.
            Route::post('/utilities/bulk-readings', [UtilityReadingController::class, 'bulkStore'])
                ->name('tenant.utilities.bulk-store');

            Route::middleware('sub-permission:utility.manage')->group(function () {
                // Landlord-configured charges: metered rates, fixed per-unit
                // fees and shared bills, assigned to the units that pay them.
                Route::post('/utilities/charges', [UtilityChargeController::class, 'store'])
                    ->name('tenant.utilities.charges.store');
                Route::put('/utilities/charges/{charge}', [UtilityChargeController::class, 'update'])
                    ->whereNumber('charge')
                    ->name('tenant.utilities.charges.update');
                Route::delete('/utilities/charges/{charge}', [UtilityChargeController::class, 'destroy'])
                    ->whereNumber('charge')
                    ->name('tenant.utilities.charges.destroy');
            });
            // Retired `/meter-readings` URLs keep working for bookmarks.
            Route::redirect('/meter-readings', '/utilities', 301);
        });

        Route::put('/units/{unit}', [UnitController::class, 'update'])
            ->middleware('sub-permission:unit.edit')
            ->name('tenant.units.update');
        Route::delete('/units/{unit}', [UnitController::class, 'destroy'])
            ->middleware('sub-permission:unit.delete')
            ->name('tenant.units.destroy');

        Route::middleware('sub-permission:occupant.manage')->group(function () {
            Route::get('/{property:slug}/occupants', [OccupantController::class, 'index'])
                ->name('tenant.occupants.index');

            // The record is a sheet on the index, not a page of its own. This URL
            // is kept for links and bookmarks; the controller validates the id
            // and redirects to the query form the page already reads, the same
            // shape the platform modules use.
            //
            // `whereNumber` is load-bearing: `/occupants/create` is registered
            // in a later group, so an unconstrained `{occupant}` would match it
            // first and 404 the create form.
            Route::get('/{property:slug}/occupants/{occupant}', [OccupantController::class, 'show'])
                ->whereNumber('occupant')
                ->name('tenant.occupants.show');
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
            // Before the /{lease} routes: an unconstrained {lease} would
            // otherwise swallow `export` as a lease id.
            Route::get('/{property:slug}/leases/export', [LeaseController::class, 'export'])
                ->name('tenant.leases.export');
            // Registered before /{lease}/remind: an unconstrained {lease}
            // would otherwise swallow remind-bulk as a lease id.
            Route::post('/{property:slug}/leases/remind-bulk', [LeaseController::class, 'remindBulk'])
                ->name('tenant.leases.remind-bulk');
            // Fixed segment before any /{lease} routes: an unconstrained
            // {lease} would otherwise swallow bulk-additional-charges.
            Route::post('/{property:slug}/leases/bulk-additional-charges', [LeaseController::class, 'bulkStoreAdditionalCharges'])
                ->name('tenant.leases.additional-charges.bulk-store');
            Route::post('/{property:slug}/leases/{lease}/remind', [LeaseController::class, 'remind'])
                ->name('tenant.leases.remind');
            // A dated rent change: history keeps every rate, so earlier
            // periods stay priced at their own rent.
            Route::post('/{property:slug}/leases/{lease}/amend-rent', [LeaseController::class, 'amendRent'])
                ->name('tenant.leases.amend-rent');
            // Free-form extras on one lease: parking, pets and other
            // tenant-specific fees the property template cannot express.
            Route::post('/{property:slug}/leases/{lease}/additional-charges', [LeaseAdditionalChargeController::class, 'store'])
                ->name('tenant.leases.additional-charges.store');
            Route::put('/{property:slug}/leases/{lease}/additional-charges/{extra}', [LeaseAdditionalChargeController::class, 'update'])
                ->whereNumber('extra')
                ->name('tenant.leases.additional-charges.update');
            Route::delete('/{property:slug}/leases/{lease}/additional-charges/{extra}', [LeaseAdditionalChargeController::class, 'destroy'])
                ->whereNumber('extra')
                ->name('tenant.leases.additional-charges.destroy');
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
        });

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

        // Notes hang off one request. Reading them is part of working the
        // request; writing one is an edit.
        Route::get('/maintenance/{record}/notes', [MaintenanceNoteController::class, 'index'])
            ->middleware('sub-permission:maintenance.manage')
            ->name('tenant.maintenance.notes.index');
        Route::post('/maintenance/{record}/notes', [MaintenanceNoteController::class, 'store'])
            ->middleware('sub-permission:maintenance.edit')
            ->name('tenant.maintenance.notes.store');

        Route::middleware('sub-permission:lease.manage_templates')->group(function () {
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
            Route::post('/{property:slug}/occupants/{occupant}/move-in', [OccupantController::class, 'moveIn'])
                ->name('tenant.occupants.move-in');
        });

        Route::middleware('sub-permission:occupant.delete')->group(function () {
            Route::delete('/{property:slug}/occupants/{occupant}', [OccupantController::class, 'destroy'])
                ->name('tenant.occupants.destroy');
        });
    });

    Route::middleware(['auth', 'organization.active'])->group(function () {
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

        Route::middleware('sub-permission:payment.manage')->group(function () {
            Route::get('/payments', [PaymentController::class, 'index'])
                ->name('tenant.payments.index');
            Route::get('/payments/create', [PaymentController::class, 'create'])
                ->name('tenant.payments.create');
            Route::post('/payments', [PaymentController::class, 'store'])
                ->name('tenant.payments.store');
            Route::get('/payments/{payment}/edit', [PaymentController::class, 'edit'])
                ->name('tenant.payments.edit');
            Route::put('/payments/{payment}', [PaymentController::class, 'update'])
                ->name('tenant.payments.update');
            Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])
                ->name('tenant.payments.destroy');
            Route::get('/payments/{payment}/receipt', [PaymentController::class, 'show'])
                ->name('tenant.payments.show');
        });

        Route::middleware('sub-permission:lease.manage')->group(function () {
            Route::post('/land-parcels/{land_parcel}/sections/{land_parcel_section}/leases', [LandParcelSectionController::class, 'lease'])
                ->name('tenant.land-parcel-sections.lease');
        });

        Route::middleware('sub-permission:staff.manage')->group(function () {
            Route::get('/staff', [StaffController::class, 'index'])
                ->name('tenant.staff.index');

            // Sub-role bundles: the landlord shapes caretaker/agent and
            // creates narrower roles here. Registered before any /staff/{id}
            // route so `roles` can never bind as a staff id.
            Route::get('/staff/roles', [SubRoleController::class, 'index'])
                ->name('tenant.staff.roles.index');
            Route::post('/staff/roles', [SubRoleController::class, 'store'])
                ->name('tenant.staff.roles.store');
            Route::put('/staff/roles/{subRole}', [SubRoleController::class, 'update'])
                ->whereNumber('subRole')
                ->name('tenant.staff.roles.update');
            Route::delete('/staff/roles/{subRole}', [SubRoleController::class, 'destroy'])
                ->whereNumber('subRole')
                ->name('tenant.staff.roles.destroy');
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

        // Central trash: soft-deleted rows grouped by module, with
        // restore and permanent delete. Reachable without a property so
        // an empty portfolio can still empty its trash.
        Route::get('/trash', [TrashController::class, 'index'])
            ->name('tenant.trash.index');
        Route::post('/trash/restore', [TrashController::class, 'restore'])
            ->name('tenant.trash.restore');
        Route::delete('/trash/force', [TrashController::class, 'force'])
            ->name('tenant.trash.force');
        Route::delete('/trash/empty', [TrashController::class, 'empty'])
            ->name('tenant.trash.empty');
        Route::delete('/trash/bulk', [TrashController::class, 'bulk'])
            ->name('tenant.trash.bulk');

        // Rich-text editor image upload (agreement templates + lease agreements).
        Route::post('/editor-images', [EditorImageController::class, 'store'])
            ->name('tenant.editor-images.store');
    });
});
