<?php

namespace App\Http\Middleware;

use App\Services\SsoService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SsoAuthMiddleware
 *
 * Middleware untuk memverifikasi JWT Bearer token dari SSO Dosen.
 * Jika valid, memetakan user ke role lokal dan menyimpannya di request.
 */
class SsoAuthMiddleware
{
    public function __construct(protected SsoService $ssoService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Bearer token diperlukan.',
            ], 401);
        }

        // Verifikasi JWT dari SSO Dosen
        $payload = $this->ssoService->verifyAndDecodeJwt($token);

        if (!$payload) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Token SSO tidak valid atau sudah expired.',
            ], 401);
        }

        // Petakan user SSO ke role lokal
        $localUser = $this->ssoService->mapToLocalRole($payload);

        // Simpan di request untuk digunakan controller
        $request->attributes->set('sso_user', $localUser);
        $request->attributes->set('sso_payload', $payload);

        return $next($request);
    }
}
