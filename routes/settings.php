<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RolesController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

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

// Platform role & central-permission management (admin via PlatformPermissionKey::ManageRoles).
Route::middleware(['auth', 'verified', 'can:manageRoles'])->group(function () {
    Route::get('settings/roles', [RolesController::class, 'index'])->name('settings.roles.index');

    Route::get('settings/roles/{role}/edit', [RolesController::class, 'edit'])->name('settings.roles.edit');
    Route::patch('settings/roles/{role}', [RolesController::class, 'update'])
        ->middleware(RequirePassword::class)
        ->name('settings.roles.update');

    Route::patch('settings/users/{user}/role', [RolesController::class, 'assignRole'])
        ->middleware(RequirePassword::class)
        ->name('settings.users.role');
});
