<?php

use App\Http\Controllers\MemberController;
use App\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Service C — Membership API Routes
| DPark Bandung — Parking Management System
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ─── SSO Routes ───────────────────────────────────────────────────────
    // Modul 1: Federated SSO — Login ke Cloud Dosen & verifikasi JWT

    Route::prefix('sso')->group(function () {

        // POST /api/v1/sso/token  → Login M2M dengan API Key
        Route::post('/token', [SsoController::class, 'getM2mToken']);

        // POST /api/v1/sso/login  → Login sebagai Warga SSO
        Route::post('/login', [SsoController::class, 'loginWarga']);

        // POST /api/v1/sso/verify → Verifikasi JWT dari SSO Dosen
        Route::post('/verify', [SsoController::class, 'verifyToken']);
    });

    // ─── Member Routes ────────────────────────────────────────────────────

    Route::prefix('members')->group(function () {

        // GET  /api/v1/members          → Admin: lihat semua member
        Route::get('/', [MemberController::class, 'index']);

        // POST /api/v1/members/verification → Verifikasi membership saat transaksi
        // [KRITIS] Endpoint ini memicu: SSO Login → SOAP Audit → AMQP Publish
        Route::post('/verification', [MemberController::class, 'verify']);

        // GET  /api/v1/members/{id}     → Detail member & status membership
        Route::get('/{id}', [MemberController::class, 'show']);
    });
});
