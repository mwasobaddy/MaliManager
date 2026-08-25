<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Audit\PlatformAuditController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\OnboardingController;
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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('auth/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('auth/onboarding', [OnboardingController::class, 'complete'])->name('onboarding.complete');

    Route::get('dashboard', [DashboardController::class, 'index'])
        ->name('dashboard')
        ->middleware('onboarded');

    Route::get('setup/first-asset', [OnboardingController::class, 'firstAsset'])
        ->name('onboarding.first-asset')
        ->middleware('onboarded');

    // Searchers (and any user with a linked person) can review their own
    // rental history across every organization.
    Route::get('searcher/rentals', [LeaseController::class, 'index'])
        ->name('searcher.rentals');
});

require __DIR__.'/settings.php';
