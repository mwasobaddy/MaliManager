<?php

use App\Http\Controllers\Api\ListingController;
use Illuminate\Support\Facades\Route;

// Public listings: no authentication. The scope only ever returns publicly
// listed units, and the resource carries no owner or tenant data.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/listings', [ListingController::class, 'index'])
        ->name('api.listings.index');
});
