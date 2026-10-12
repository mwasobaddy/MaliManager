<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Platform\AssistantController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\PlatformController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Searcher\LeaseController;
use App\Http\Controllers\Searcher\MyMaintenanceController;
use App\Http\Controllers\Searcher\SearcherAssistantController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

require __DIR__.'/auth.php';

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('admin', [PlatformController::class, 'index'])->name('admin.dashboard');
    Route::post('admin/impersonate/{organization}/{user}', [PlatformController::class, 'impersonate'])
        ->name('admin.impersonate');
    Route::get('admin/assistant', [AssistantController::class, 'page'])
        ->middleware('can:aiUse')
        ->name('admin.assistant.page');
    Route::post('admin/assistant/ask', [AssistantController::class, 'ask'])
        ->middleware('can:aiUse')
        ->name('admin.assistant.ask');

    // Assistant conversation history.
    Route::get('admin/assistant/history', [AssistantController::class, 'history'])
        ->middleware('can:aiUse')
        ->name('admin.assistant.history');
    Route::get('admin/assistant/conversations/{id}', [AssistantController::class, 'showConversation'])
        ->middleware('can:aiUse')
        ->name('admin.assistant.conversation');
    Route::delete('admin/assistant/conversations/{id}', [AssistantController::class, 'destroyConversation'])
        ->middleware('can:aiUse')
        ->name('admin.assistant.conversation.destroy');
});

// Platform-wide audit, user management and organization management all live in
// routes/platform.php. They used to be declared here as well, and since this
// file requires that one, every route was registered twice — with the older
// copy here silently losing by load order. `require __DIR__.'/platform.php';`
// further down covers all three modules.

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
        ->middleware('can:aiUse')
        ->name('searcher.assistant.page');
    Route::post('searcher/assistant/ask', [SearcherAssistantController::class, 'ask'])
        ->middleware('can:aiUse')
        ->name('searcher.assistant.ask');

    // Assistant conversation history.
    Route::get('searcher/assistant/history', [SearcherAssistantController::class, 'history'])
        ->middleware('can:aiUse')
        ->name('searcher.assistant.history');
    Route::get('searcher/assistant/conversations/{id}', [SearcherAssistantController::class, 'showConversation'])
        ->middleware('can:aiUse')
        ->name('searcher.assistant.conversation');
    Route::delete('searcher/assistant/conversations/{id}', [SearcherAssistantController::class, 'destroyConversation'])
        ->middleware('can:aiUse')
        ->name('searcher.assistant.conversation.destroy');
});

// Global command-palette search. Tenant-aware on tenant domains (resolved
// from the hostname inside the service), cross-organization for platform
// admins on the central domain.
Route::middleware(['auth'])->group(function () {
    Route::get('search', [SearchController::class, 'index'])->name('search');
});

Route::inertia('suspended', 'auth/suspended')->name('suspended');

require __DIR__.'/platform.php';
require __DIR__.'/settings.php';
