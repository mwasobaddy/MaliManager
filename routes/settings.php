<?php

use App\Http\Controllers\Settings\PersonalAiController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

// User self-service settings.
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');

// Personal AI provider key (own-data scoped).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/personal-ai', [PersonalAiController::class, 'edit'])
        ->name('settings.personal-ai.edit');
    Route::put('settings/personal-ai', [PersonalAiController::class, 'update'])
        ->name('settings.personal-ai.update');
    Route::delete('settings/personal-ai', [PersonalAiController::class, 'destroyKey'])
        ->name('settings.personal-ai.destroy');
});
