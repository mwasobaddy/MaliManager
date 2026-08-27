<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Audit\PlatformAuditController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\MyMaintenanceController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Organizations\OrganizationController;
use App\Http\Controllers\SearcherAssistantController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['guest'])->group(function () {
    Route::post('auth/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');

    Route::get('auth/otp', [OtpController::class, 'show'])->name('login.otp');
    Route::post('auth/otp', [OtpController::class, 'verify'])->middleware('throttle:otp')->name('login.otp.verify');
    Route::post('auth/otp/resend', [OtpController::class, 'resend'])->middleware('throttle:otp')->name('login.otp.resend');

    Route::get('auth/{provider}/redirect', [SocialiteController::class, 'redirect'])
        ->whereIn('provider', ['google'])
        ->name('auth.socialite.redirect');

    Route::get('auth/{provider}/callback', [SocialiteController::class, 'callback'])
        ->whereIn('provider', ['google'])
        ->name('auth.socialite.callback');
});

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('admin', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::post('admin/impersonate/{organization}/{user}', [AdminController::class, 'impersonate'])
        ->name('admin.impersonate');
});

// Platform-wide audit view is gated by the central `viewAnyAudit` gate
// (see App\Providers\AuthServiceProvider), not the `admin` middleware.
// Export is separately gated by `exportAudit` so viewing does not imply
// exporting.
Route::middleware(['auth', 'verified', 'can:viewAnyAudit'])->group(function () {
    Route::get('platform/audit', [PlatformAuditController::class, 'index'])
        ->name('platform.audit.index');
});

Route::middleware(['auth', 'verified', 'can:exportAudit'])->group(function () {
    Route::get('platform/audit/export', [PlatformAuditController::class, 'export'])
        ->name('platform.audit.export');
});

// Central user management module, gated by central user.* permissions.
Route::middleware(['auth', 'verified', 'can:manageUsers'])->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/export', [UserController::class, 'export'])->name('users.export')->middleware('can:exportUsers');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create')->middleware('can:createUsers');
    Route::post('users', [UserController::class, 'store'])->name('users.store')->middleware('can:createUsers');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit')->middleware('can:editUsers');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('can:editUsers');
    Route::patch('users/{user}/status', [UserController::class, 'setStatus'])->name('users.status')->middleware('can:editUsers');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('can:deleteUsers');
});

// Central organization management module, gated by central organization.* permissions.
Route::middleware(['auth', 'verified', 'can:manageOrganizations'])->group(function () {
    Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('organizations/export', [OrganizationController::class, 'export'])->name('organizations.export')->middleware('can:exportOrganizations');
    Route::get('organizations/create', [OrganizationController::class, 'create'])->name('organizations.create')->middleware('can:createOrganizations');
    Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store')->middleware('can:createOrganizations');
    Route::get('organizations/{organization}/edit', [OrganizationController::class, 'edit'])->name('organizations.edit')->middleware('can:editOrganizations');
    Route::patch('organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update')->middleware('can:editOrganizations');
    Route::delete('organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy')->middleware('can:deleteOrganizations');
});

// Central plan management module, gated by central plan.* permissions.
Route::middleware(['auth', 'verified', 'can:managePlans'])->group(function () {
    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
    Route::get('plans/create', [PlanController::class, 'create'])->name('plans.create')->middleware('can:createPlans');
    Route::post('plans', [PlanController::class, 'store'])->name('plans.store')->middleware('can:createPlans');
    Route::get('plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit')->middleware('can:editPlans');
    Route::patch('plans/{plan}', [PlanController::class, 'update'])->name('plans.update')->middleware('can:editPlans');
    Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy')->middleware('can:deletePlans');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('auth/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('auth/onboarding', [OnboardingController::class, 'complete'])->name('onboarding.complete');

    Route::get('dashboard', [DashboardController::class, 'index'])
        ->name('dashboard')
        ->middleware('onboarded');

    Route::post('property-picker/acknowledge', [DashboardController::class, 'acknowledge'])
        ->name('property-picker.acknowledge');

    Route::get('setup/first-asset', [OnboardingController::class, 'firstAsset'])
        ->name('onboarding.first-asset')
        ->middleware('onboarded');

    // Searchers (and any user with a linked person) can review their own
    // rental history across every organization.
    Route::get('searcher/rentals', [LeaseController::class, 'index'])
        ->name('searcher.rentals');

    // Occupant-side maintenance requests (active-lease holders only; the
    // service enforces the lease requirement).
    Route::get('searcher/maintenance', [MyMaintenanceController::class, 'index'])
        ->name('searcher.maintenance.index');
    Route::post('searcher/maintenance', [MyMaintenanceController::class, 'store'])
        ->name('searcher.maintenance.store');

    // Occupant AI assistant (own-data scoped).
    Route::get('searcher/assistant', [SearcherAssistantController::class, 'page'])
        ->name('searcher.assistant.page');
    Route::post('searcher/assistant/ask', [SearcherAssistantController::class, 'ask'])
        ->name('searcher.assistant.ask');
});

require __DIR__.'/settings.php';
