<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['guest'])->group(function () {
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');

    Route::get('login/otp', [OtpController::class, 'show'])->name('login.otp');
    Route::post('login/otp', [OtpController::class, 'verify'])->middleware('throttle:otp')->name('login.otp.verify');
    Route::post('login/otp/resend', [OtpController::class, 'resend'])->middleware('throttle:otp')->name('login.otp.resend');

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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding', [OnboardingController::class, 'complete'])->name('onboarding.complete');

    Route::inertia('dashboard', 'dashboard')->name('dashboard')->middleware('onboarded');
});

require __DIR__.'/settings.php';
