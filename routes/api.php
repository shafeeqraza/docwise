<?php

use App\Http\Controllers\V1\Api\SuperAdminAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Api\SuperAdminCompanyController;

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

// Super Admin Company Management Routes
Route::prefix('superadmin')->middleware(['auth:sanctum', 'superadmin'])->group(function () {
    Route::prefix('companies')->group(function () {
        // List all companies
        Route::get('/', [SuperAdminCompanyController::class, 'index'])
            ->name('superadmin.companies.index');

        // Create new company
        Route::post('/', [SuperAdminCompanyController::class, 'store'])
            ->name('superadmin.companies.store');

        // Get company by ID or UUID
        Route::get('/{company}', [SuperAdminCompanyController::class, 'show'])
            ->name('superadmin.companies.show');

        // Update company
        Route::put('/{company}', [SuperAdminCompanyController::class, 'update'])
            ->name('superadmin.companies.update');

        // Soft delete company
        Route::delete('/{company}', [SuperAdminCompanyController::class, 'destroy'])
            ->name('superadmin.companies.destroy');

        // Suspend company
        Route::post('/{company}/suspend', [SuperAdminCompanyController::class, 'suspend'])
            ->name('superadmin.companies.suspend');

        // Activate company
        Route::post('/{company}/activate', [SuperAdminCompanyController::class, 'activate'])
            ->name('superadmin.companies.activate');
    });
});
