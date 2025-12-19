<?php

use App\Http\Controllers\V1\Api\SuperAdminAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Super Admin Authentication Routes
Route::prefix('superadmin/auth')->group(function () {
    // Login endpoint with rate limiting (5 attempts per minute per IP)
    Route::post('/login', [SuperAdminAuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('superadmin.login');

    // Logout endpoint (requires authentication)
    Route::post('/logout', [SuperAdminAuthController::class, 'logout'])
        ->middleware(['auth:sanctum', 'superadmin'])
        ->name('superadmin.logout');

    // Get current authenticated superadmin
    Route::get('/me', [SuperAdminAuthController::class, 'me'])
        ->middleware(['auth:sanctum', 'superadmin'])
        ->name('superadmin.me');
});
