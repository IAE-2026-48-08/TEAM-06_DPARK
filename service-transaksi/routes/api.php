<?php

use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('apikey')->group(function () {
    Route::get('/v1/transactions', [TransactionController::class, 'index']);
    Route::get('/v1/transactions/{id}', [TransactionController::class, 'show']);
    Route::post('/v1/transactions', [TransactionController::class, 'store']);
    Route::put('/v1/transactions/{id}', [TransactionController::class, 'update']);
});