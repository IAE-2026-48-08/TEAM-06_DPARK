<?php

namespace App\Http\Controllers;

use App\Services\SsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * SsoController
 *
 * Controller untuk endpoint SSO-related di DPark Membership Service.
 * Menangani login SSO dan verifikasi JWT.
 */
class SsoController extends Controller
{
    public function __construct(
        protected SsoService $ssoService
    ) {}

    /**
     * Login M2M ke SSO Dosen menggunakan API Key.
     * Mengembalikan JWT token yang bisa digunakan untuk request selanjutnya.
     */
    #[OA\Post(
        path: '/sso/token',
        summary: 'Login M2M ke SSO Dosen',
        tags: ['SSO']
    )]
    #[OA\Response(response: 200, description: 'JWT token berhasil didapat')]
    #[OA\Response(response: 502, description: 'SSO tidak dapat dijangkau')]
    public function getM2mToken(): JsonResponse
    {
        $result = $this->ssoService->loginM2M();

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data'    => null,
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => [
                'access_token' => $result['token'],
                'token_type'   => 'Bearer',
            ],
        ]);
    }

    /**
     * Login sebagai Warga SSO.
     */
    #[OA\Post(
        path: '/sso/login',
        summary: 'Login sebagai Warga ke SSO Dosen',
        tags: ['SSO']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'warga31@ktp.iae.id'),
                new OA\Property(property: 'password', type: 'string', example: 'KtpDigital2026!'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Login berhasil')]
    public function loginWarga(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $result = $this->ssoService->loginAsWarga(
            $request->input('email'),
            $request->input('password')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data'    => null,
            ], 401);
        }

        // Petakan ke role lokal jika payload tersedia
        $localUser = null;
        if ($result['payload']) {
            $localUser = $this->ssoService->mapToLocalRole($result['payload']);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => [
                'access_token' => $result['token'],
                'token_type'   => 'Bearer',
                'sso_payload'  => $result['payload'],
                'local_user'   => $localUser,
            ],
        ]);
    }

    /**
     * Verifikasi dan decode JWT dari SSO Dosen.
     */
    #[OA\Post(
        path: '/sso/verify',
        summary: 'Verifikasi JWT dari SSO Dosen',
        tags: ['SSO']
    )]
    public function verifyToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $payload = $this->ssoService->verifyAndDecodeJwt($request->input('token'));

        if (!$payload) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid atau sudah expired.',
                'data'    => null,
            ], 401);
        }

        $localUser = $this->ssoService->mapToLocalRole($payload);

        return response()->json([
            'success' => true,
            'message' => 'Token valid.',
            'data'    => [
                'payload'    => $payload,
                'local_user' => $localUser,
            ],
        ]);
    }
}
