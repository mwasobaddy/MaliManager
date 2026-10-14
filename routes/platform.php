<?php

use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\Platform\AssistantController;
use App\Http\Controllers\Platform\AuditController;
use App\Http\Controllers\Platform\OrganizationController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\PlatformController;
use App\Http\Controllers\Platform\PlatformExpenseController;
use App\Http\Controllers\Platform\RolesController;
use App\Http\Controllers\Platform\SubscriptionPaymentController;
use App\Http\Controllers\Platform\UserController;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlatformExpense;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

// Ending an impersonation. Deliberately *not* behind the `admin` middleware:
// the current user is the impersonated owner, who is by definition not an
// admin, so gating it there would make the only way out unreachable.
Route::post('impersonation/stop', [ImpersonationController::class, 'stop'])
    ->middleware('auth')
    ->name('impersonation.stop');

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

/**
 * Send a legacy module path to its new home, keeping the query string.
 *
 * Both modules moved from `/users` and `/organizations` to `/platform/...`
 * alongside the rest of the central admin. A bookmarked overlay link like
 * `/organizations?mode=edit&organization=5` has to keep working, so the query
 * is carried across — `redirect()->route()` would silently drop it and land
 * the visitor on a bare list with the sheet closed.
 */
$legacyModuleRedirect = function (string $target) {
    return function (Request $request) use ($target) {
        $query = $request->query();

        return redirect()->to(
            route($target).($query === [] ? '' : '?'.http_build_query($query)),
        );
    };
};

// Central user management module, gated by central user.* permissions.
//
// Prefixed with `platform/` and named `platform.users.*` so it sits with the other
// central modules and the controller lives in App\Http\Controllers\Platform, like
// plans, expenses, audit and roles. It used to be the odd one out: a bare
// `/users` and `/organizations` at the top level.
//
// Create and edit are overlays, not pages — the forms live in
// ResponsiveOverlay on platform/users/index and are addressed by query params. The GET
// routes below redirect so bookmarks from before the overlay landed still open
// the overlay instead of 404ing.
Route::middleware(['auth', 'verified'])
    ->get('users', $legacyModuleRedirect('platform.users.index'))
    ->name('legacy.users');

Route::middleware(['auth', 'verified', 'can:manageUsers'])->group(function () {
    Route::get('platform/users', [UserController::class, 'index'])->name('platform.users.index');
    Route::post('platform/users/import', [UserController::class, 'import'])->name('platform.users.import')->middleware('can:importUsers');
    Route::get('platform/users/export', [UserController::class, 'export'])->name('platform.users.export')->middleware('can:exportUsers');
    Route::get('platform/users/create', fn () => redirect()->route('platform.users.index', ['create' => 1]))
        ->name('platform.users.create')
        ->middleware('can:createUsers');
    Route::post('platform/users', [UserController::class, 'store'])->name('platform.users.store')->middleware('can:createUsers');
    Route::get('platform/users/{user}/edit', fn (User $user) => redirect()
        ->route('platform.users.index', ['mode' => 'edit', 'user' => $user->id]))
        ->name('platform.users.edit')
        ->middleware('can:editUsers');
    Route::patch('platform/users/{user}', [UserController::class, 'update'])->name('platform.users.update')->middleware('can:editUsers');
    Route::patch('platform/users/{user}/status', [UserController::class, 'setStatus'])->name('platform.users.status')->middleware('can:editUsers');
    Route::delete('platform/users/{user}', [UserController::class, 'destroy'])->name('platform.users.destroy')->middleware('can:deleteUsers');
});

// Central organization management module, gated by central organization.* permissions.
//
// This block used to be duplicated verbatim in routes/web.php, which also
// required this file — so every route below was registered twice and the copy
// here won purely by load order. web.php's copy had quietly grown three extra
// routes (import, status, impersonate) that this one never received. Keep the
// module in one place; if you add a route here, add it here only.
Route::middleware(['auth', 'verified'])
    ->get('organizations', $legacyModuleRedirect('platform.organizations.index'))
    ->name('legacy.organizations');

Route::middleware(['auth', 'verified', 'can:manageOrganizations'])->group(function () {
    Route::get('platform/organizations', [OrganizationController::class, 'index'])->name('platform.organizations.index');
    Route::post('platform/organizations/import', [OrganizationController::class, 'import'])->name('platform.organizations.import')->middleware('can:importOrganizations');
    Route::get('platform/organizations/export', [OrganizationController::class, 'export'])->name('platform.organizations.export')->middleware('can:exportOrganizations');

    // Create and edit are overlays, not pages: the forms live in
    // ResponsiveOverlay on organizations/index and are addressed by query
    // params. These redirects exist so bookmarks and shared links from before
    // the overlay landed still land on the open overlay instead of a 404.
    Route::get('platform/organizations/create', fn () => redirect()
        ->route('platform.organizations.index', ['create' => 1]))
        ->name('platform.organizations.create')
        ->middleware('can:createOrganizations');
    Route::get('platform/organizations/{organization}/edit', fn (Organization $organization) => redirect()
        ->route('platform.organizations.index', ['mode' => 'edit', 'organization' => $organization->id]))
        ->name('platform.organizations.edit')
        ->middleware('can:editOrganizations');

    Route::post('platform/organizations', [OrganizationController::class, 'store'])->name('platform.organizations.store')->middleware('can:createOrganizations');
    Route::patch('platform/organizations/{organization}', [OrganizationController::class, 'update'])->name('platform.organizations.update')->middleware('can:editOrganizations');
    Route::patch('platform/organizations/{organization}/status', [OrganizationController::class, 'setStatus'])
        ->name('platform.organizations.status')
        ->middleware('can:editOrganizations');
    Route::delete('platform/organizations/{organization}', [OrganizationController::class, 'destroy'])->name('platform.organizations.destroy')->middleware('can:deleteOrganizations');
    Route::post('platform/organizations/{organization}/impersonate', [PlatformController::class, 'impersonateOwner'])
        ->name('platform.organizations.impersonate')
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
    Route::post('platform/plans/import', [PlanController::class, 'import'])->name('platform.plans.import')->middleware('can:importPlans');
    Route::get('platform/plans/export', [PlanController::class, 'export'])->name('platform.plans.export')->middleware('can:exportPlans');
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
    Route::post('platform/subscription-payments/import', [SubscriptionPaymentController::class, 'import'])->name('platform.subscription-payments.import')->middleware('can:importSubscriptionPayments');
    Route::get('platform/subscription-payments/export', [SubscriptionPaymentController::class, 'export'])->name('platform.subscription-payments.export')->middleware('can:exportSubscriptionPayments');
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
    Route::post('platform/expenses/import', [PlatformExpenseController::class, 'import'])->name('platform.expenses.import')->middleware('can:importPlatformExpenses');
    Route::get('platform/expenses/export', [PlatformExpenseController::class, 'export'])->name('platform.expenses.export')->middleware('can:exportPlatformExpenses');
});

// Platform role & central-permission management (admin via PlatformPermissionKey::ManageRoles).
//
// Editing a role's permissions is an overlay on the index, addressed by query
// params, so the old page route redirects there rather than rendering a second
// copy of the checkbox list.
Route::middleware(['auth', 'verified', 'can:manageRoles'])->group(function () {
    Route::get('platform/roles', [RolesController::class, 'index'])->name('platform.roles.index');
    Route::get('platform/roles/{role}/edit', fn (Role $role) => redirect()
        ->route('platform.roles.index', ['mode' => 'edit', 'role' => $role->id]))
        ->name('platform.roles.edit');
    Route::patch('platform/roles/{role}', [RolesController::class, 'update'])
        ->middleware(RequirePassword::class)
        ->name('platform.roles.update');
});
