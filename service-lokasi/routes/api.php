<?php

use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Middleware\VerifyJwtSSO;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/v1/locations', [LocationController::class, 'index']);
Route::get('/v1/locations/{id}', [LocationController::class, 'show']);

// Protected routes (Admin - API Key)
Route::middleware('api.key')->group(function () {
    Route::post('/v1/locations', [LocationController::class, 'store']);
});

// Protected routes (SSO JWT - Critical Transaction)
Route::middleware(VerifyJwtSSO::class)->group(function () {
    Route::post('/v1/locations/{id}/check-in', [LocationController::class, 'checkIn']);
});

// Internal route — dipanggil dari service-transaksi (tanpa SSO JWT)
Route::middleware('api.key')->group(function () {
    Route::post('/v1/locations/{id}/internal-checkin', [LocationController::class, 'checkIn']);
});