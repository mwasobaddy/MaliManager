<?php

use App\Http\Controllers\Organizations\OrganizationController;
use App\Http\Controllers\Platform\AssistantController;
use App\Http\Controllers\Platform\AuditController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\PlatformController;
use App\Http\Controllers\Platform\PlatformExpenseController;
use App\Http\Controllers\Platform\RolesController;
use App\Http\Controllers\Platform\SubscriptionPaymentController;
use App\Http\Controllers\Users\UserController;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlatformExpense;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

// Central platform administration, gated by the `admin` middleware.
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('platform', [PlatformController::class, 'index'])->name('platform.dashboard');
    Route::post('platform/impersonate/{organization}/{user}', [PlatformController::class, 'impersonate'])
        ->name('platform.impersonate');
    Route::get('platform/assistant', [AssistantController::class, 'page'])
        ->middleware('can:aiUse')
        ->name('platform.assistant.page');
    Route::post('platform/assistant/ask', [AssistantController::class, 'ask'])
        ->middleware('can:aiUse')
        ->name('platform.assistant.ask');

    // Assistant conversation history.
    Route::get('platform/assistant/history', [AssistantController::class, 'history'])
        ->middleware('can:aiUse')
        ->name('platform.assistant.history');
    Route::get('platform/assistant/conversations/{id}', [AssistantController::class, 'showConversation'])
        ->middleware('can:aiUse')
        ->name('platform.assistant.conversation');
    Route::delete('platform/assistant/conversations/{id}', [AssistantController::class, 'destroyConversation'])
        ->middleware('can:aiUse')
        ->name('platform.assistant.conversation.destroy');
});

// Platform-wide audit view is gated by the central `viewAnyAudit` gate
// (see App\Providers\AuthServiceProvider), not the `admin` middleware.
// Export is separately gated by `exportAudit` so viewing does not imply
// exporting.
Route::middleware(['auth', 'verified', 'can:viewAnyAudit'])->group(function () {
    Route::get('platform/audit', [AuditController::class, 'index'])
        ->name('platform.audit.index');
});

Route::middleware(['auth', 'verified', 'can:exportAudit'])->group(function () {
    Route::get('platform/audit/export', [AuditController::class, 'export'])
        ->name('platform.audit.export');
});

// Central user management module, gated by central user.* permissions.
//
// Create and edit are overlays, not pages — the forms live in
// ResponsiveOverlay on users/index and are addressed by query params. The GET
// routes below redirect so bookmarks from before the overlay landed still open
// the overlay instead of 404ing.
Route::middleware(['auth', 'verified', 'can:manageUsers'])->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users/import', [UserController::class, 'import'])->name('users.import')->middleware('can:importUsers');
    Route::get('users/export', [UserController::class, 'export'])->name('users.export')->middleware('can:exportUsers');
    Route::get('users/create', fn () => redirect()->route('users.index', ['create' => 1]))
        ->name('users.create')
        ->middleware('can:createUsers');
    Route::post('users', [UserController::class, 'store'])->name('users.store')->middleware('can:createUsers');
    Route::get('users/{user}/edit', fn (User $user) => redirect()
        ->route('users.index', ['mode' => 'edit', 'user' => $user->id]))
        ->name('users.edit')
        ->middleware('can:editUsers');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('can:editUsers');
    Route::patch('users/{user}/status', [UserController::class, 'setStatus'])->name('users.status')->middleware('can:editUsers');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('can:deleteUsers');
});

// Central organization management module, gated by central organization.* permissions.
//
// This block used to be duplicated verbatim in routes/web.php, which also
// required this file — so every route below was registered twice and the copy
// here won purely by load order. web.php's copy had quietly grown three extra
// routes (import, status, impersonate) that this one never received. Keep the
// module in one place; if you add a route here, add it here only.
Route::middleware(['auth', 'verified', 'can:manageOrganizations'])->group(function () {
    Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('organizations/import', [OrganizationController::class, 'import'])->name('organizations.import')->middleware('can:importOrganizations');
    Route::get('organizations/export', [OrganizationController::class, 'export'])->name('organizations.export')->middleware('can:exportOrganizations');

    // Create and edit are overlays, not pages: the forms live in
    // ResponsiveOverlay on organizations/index and are addressed by query
    // params. These redirects exist so bookmarks and shared links from before
    // the overlay landed still land on the open overlay instead of a 404.
    Route::get('organizations/create', fn () => redirect()
        ->route('organizations.index', ['create' => 1]))
        ->name('organizations.create')
        ->middleware('can:createOrganizations');
    Route::get('organizations/{organization}/edit', fn (Organization $organization) => redirect()
        ->route('organizations.index', ['mode' => 'edit', 'organization' => $organization->id]))
        ->name('organizations.edit')
        ->middleware('can:editOrganizations');

    Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store')->middleware('can:createOrganizations');
    Route::patch('organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update')->middleware('can:editOrganizations');
    Route::patch('organizations/{organization}/status', [OrganizationController::class, 'setStatus'])
        ->name('organizations.status')
        ->middleware('can:editOrganizations');
    Route::delete('organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy')->middleware('can:deleteOrganizations');
    Route::post('organizations/{organization}/impersonate', [PlatformController::class, 'impersonateOwner'])
        ->name('organizations.impersonate')
        ->middleware(['admin', 'can:impersonateOrganizations']);
});

// Central plan management module, gated by central plan.* permissions.
//
// Create and edit are overlays on the index, addressed by query params, so the
// old page routes redirect there instead of rendering a second copy of the form.
Route::middleware(['auth', 'verified', 'can:managePlans'])->group(function () {
    Route::get('platform/plans', [PlanController::class, 'index'])->name('platform.plans.index');
    Route::get('platform/plans/create', fn () => redirect()->route('platform.plans.index', ['create' => 1]))
        ->name('platform.plans.create')
        ->middleware('can:createPlans');
    Route::post('platform/plans', [PlanController::class, 'store'])->name('platform.plans.store')->middleware('can:createPlans');
    Route::get('platform/plans/{plan}/edit', fn (Plan $plan) => redirect()
        ->route('platform.plans.index', ['mode' => 'edit', 'plan' => $plan->id]))
        ->name('platform.plans.edit')
        ->middleware('can:editPlans');
    Route::patch('platform/plans/{plan}', [PlanController::class, 'update'])->name('platform.plans.update')->middleware('can:editPlans');
    Route::patch('platform/plans/{plan}/status', [PlanController::class, 'setStatus'])->name('platform.plans.status')->middleware('can:editPlans');
    Route::delete('platform/plans/{plan}', [PlanController::class, 'destroy'])->name('platform.plans.destroy')->middleware('can:deletePlans');
});

// Central subscription-income module, gated by central subscription.* permissions.
//
// Create and edit are overlays on the index, addressed by query params, so the
// old page routes redirect there instead of rendering a second copy of the form.
Route::middleware(['auth', 'verified', 'can:manageSubscriptionPayments'])->group(function () {
    Route::get('platform/subscription-payments', [SubscriptionPaymentController::class, 'index'])->name('platform.subscription-payments.index');
    Route::get('platform/subscription-payments/create', fn () => redirect()->route('platform.subscription-payments.index', ['create' => 1]))
        ->name('platform.subscription-payments.create')
        ->middleware('can:createSubscriptionPayments');
    Route::post('platform/subscription-payments', [SubscriptionPaymentController::class, 'store'])->name('platform.subscription-payments.store')->middleware('can:createSubscriptionPayments');
    Route::get('platform/subscription-payments/{subscriptionPayment}/edit', fn (SubscriptionPayment $subscriptionPayment) => redirect()
        ->route('platform.subscription-payments.index', ['mode' => 'edit', 'payment' => $subscriptionPayment->id]))
        ->name('platform.subscription-payments.edit')
        ->middleware('can:editSubscriptionPayments');
    Route::patch('platform/subscription-payments/{subscriptionPayment}', [SubscriptionPaymentController::class, 'update'])->name('platform.subscription-payments.update')->middleware('can:editSubscriptionPayments');
    Route::delete('platform/subscription-payments/{subscriptionPayment}', [SubscriptionPaymentController::class, 'destroy'])->name('platform.subscription-payments.destroy')->middleware('can:deleteSubscriptionPayments');
});

// Central platform-expense module, gated by central platform-expense.* permissions.
//
// Create and edit are overlays on the index, addressed by query params, so the
// old page routes redirect there instead of rendering a second copy of the form.
Route::middleware(['auth', 'verified', 'can:managePlatformExpenses'])->group(function () {
    Route::get('platform/expenses', [PlatformExpenseController::class, 'index'])->name('platform.expenses.index');
    Route::get('platform/expenses/create', fn () => redirect()->route('platform.expenses.index', ['create' => 1]))
        ->name('platform.expenses.create')
        ->middleware('can:createPlatformExpenses');
    Route::post('platform/expenses', [PlatformExpenseController::class, 'store'])->name('platform.expenses.store')->middleware('can:createPlatformExpenses');
    Route::get('platform/expenses/{platformExpense}/edit', fn (PlatformExpense $platformExpense) => redirect()
        ->route('platform.expenses.index', ['mode' => 'edit', 'expense' => $platformExpense->id]))
        ->name('platform.expenses.edit')
        ->middleware('can:editPlatformExpenses');
    Route::patch('platform/expenses/{platformExpense}', [PlatformExpenseController::class, 'update'])->name('platform.expenses.update')->middleware('can:editPlatformExpenses');
    Route::delete('platform/expenses/{platformExpense}', [PlatformExpenseController::class, 'destroy'])->name('platform.expenses.destroy')->middleware('can:deletePlatformExpenses');
});

// Platform role & central-permission management (admin via PlatformPermissionKey::ManageRoles).
Route::middleware(['auth', 'verified', 'can:manageRoles'])->group(function () {
    Route::get('platform/roles', [RolesController::class, 'index'])->name('platform.roles.index');
    Route::get('platform/roles/{role}/edit', [RolesController::class, 'edit'])->name('platform.roles.edit');
    Route::patch('platform/roles/{role}', [RolesController::class, 'update'])
        ->middleware(RequirePassword::class)
        ->name('platform.roles.update');
    Route::patch('platform/users/{user}/role', [RolesController::class, 'assignRole'])
        ->middleware(RequirePassword::class)
        ->name('platform.users.role');
});
