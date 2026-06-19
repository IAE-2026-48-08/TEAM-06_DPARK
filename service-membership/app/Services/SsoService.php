<?php

namespace App\Services;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SsoService — Modul 1: Federated SSO
 *
 * Menangani:
 * 1. Login M2M ke SSO Dosen (mendapatkan Bearer JWT)
 * 2. Verifikasi JWT menggunakan JWKS (RS256)
 * 3. Pemetaan user SSO ke roles lokal
 */
class SsoService
{
    protected string $ssoBaseUrl;
    protected string $apiKey;
    protected string $nim;

    public function __construct()
    {
        $this->ssoBaseUrl = config('services.iae_sso.base_url', 'https://iae-sso.virtualfri.id');
        $this->apiKey     = config('services.iae_sso.api_key', 'KEY-MHS-169');
        $this->nim        = config('services.iae_sso.nim', '102022400119');
    }

    /**
     * Login M2M ke SSO Dosen menggunakan API Key.
     * Mengembalikan Bearer JWT token.
     *
     * @return array{success: bool, token: string|null, message: string}
     */
    public function loginM2M(): array
    {
        try {
            $response = Http::timeout(10)
                ->post("{$this->ssoBaseUrl}/api/v1/auth/token", [
                    'api_key' => $this->apiKey,
                    'nim'     => $this->nim,
                ]);

            if ($response->successful()) {
                $data  = $response->json();
                $token = $data['access_token'] ?? $data['token'] ?? null;

                if ($token) {
                    // Cache token selama 50 menit (biasanya JWT valid 60 menit)
                    Cache::put('iae_sso_m2m_token', $token, now()->addMinutes(50));

                    Log::info('[SSO] M2M login berhasil', [
                        'token_preview' => substr($token, 0, 20) . '...',
                    ]);

                    return [
                        'success' => true,
                        'token'   => $token,
                        'message' => 'Login SSO berhasil.',
                    ];
                }
            }

            Log::error('[SSO] M2M login gagal', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [
                'success' => false,
                'token'   => null,
                'message' => 'Login SSO gagal: ' . $response->body(),
            ];
        } catch (Exception $e) {
            Log::error('[SSO] Exception saat M2M login', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'token'   => null,
                'message' => 'SSO tidak dapat dijangkau: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Login sebagai Warga (user biasa) ke SSO Dosen.
     *
     * @return array{success: bool, token: string|null, payload: array|null, message: string}
     */
    public function loginAsWarga(string $email, string $password): array
    {
        try {
            $response = Http::timeout(10)
                ->post("{$this->ssoBaseUrl}/api/v1/auth/token", [
                    'email'    => $email,
                    'password' => $password,
                ]);

            if ($response->successful()) {
                $data  = $response->json();
                $token = $data['access_token'] ?? $data['token'] ?? null;

                if ($token) {
                    $payload = $this->verifyAndDecodeJwt($token);

                    Log::info('[SSO] Warga login berhasil', [
                        'email'   => $email,
                        'payload' => $payload,
                    ]);

                    return [
                        'success' => true,
                        'token'   => $token,
                        'payload' => $payload,
                        'message' => 'Login warga SSO berhasil.',
                    ];
                }
            }

            return [
                'success' => false,
                'token'   => null,
                'payload' => null,
                'message' => 'Login warga gagal: ' . $response->body(),
            ];
        } catch (Exception $e) {
            Log::error('[SSO] Exception saat login warga', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'token'   => null,
                'payload' => null,
                'message' => 'Gagal login warga: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Ambil JWKS (public key) dari SSO Dosen dan cache selama 1 jam.
     */
    public function getJwks(): array
    {
        return Cache::remember('iae_sso_jwks', now()->addHour(), function () {
            $response = Http::timeout(10)->get("{$this->ssoBaseUrl}/api/v1/auth/jwks");

            if ($response->successful()) {
                return $response->json();
            }

            // Fallback ke alias OIDC
            $response = Http::timeout(10)->get("{$this->ssoBaseUrl}/.well-known/jwks.json");

            return $response->json() ?? [];
        });
    }

    /**
     * Verifikasi dan decode JWT menggunakan RS256 public key dari JWKS.
     *
     * @return array|null — Payload JWT jika valid, null jika tidak valid
     */
    public function verifyAndDecodeJwt(string $token): ?array
    {
        try {
            $jwks = $this->getJwks();

            if (empty($jwks)) {
                Log::warning('[SSO] JWKS kosong, tidak bisa verifikasi JWT');
                return null;
            }

            // Parse JWKS menggunakan firebase/php-jwt
            $keys    = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($token, $keys);

            return (array) $decoded;
        } catch (Exception $e) {
            Log::warning('[SSO] Gagal verifikasi JWT', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Petakan user SSO ke role lokal berdasarkan payload JWT.
     * Menyimpan/update data user di tabel local_roles.
     *
     * @return array{sso_sub: string, email: string, local_role: string}
     */
    public function mapToLocalRole(array $jwtPayload): array
    {
        $sub   = $jwtPayload['sub'] ?? $jwtPayload['user_id'] ?? 'unknown';
        $email = $jwtPayload['email'] ?? '';
        $roles = $jwtPayload['roles'] ?? $jwtPayload['role'] ?? [];

        // Logika pemetaan role SSO → role lokal DPark Membership
        $localRole = $this->resolveLocalRole($roles, $email);

        // Simpan atau update di tabel local_roles
        \App\Models\LocalRole::updateOrCreate(
            ['sso_sub' => $sub],
            [
                'email'       => $email,
                'sso_roles'   => is_array($roles) ? implode(',', $roles) : $roles,
                'local_role'  => $localRole,
                'jwt_payload' => json_encode($jwtPayload),
                'last_seen'   => now(),
            ]
        );

        Log::info('[SSO] User dipetakan ke role lokal', [
            'sso_sub'    => $sub,
            'email'      => $email,
            'local_role' => $localRole,
        ]);

        return [
            'sso_sub'    => $sub,
            'email'      => $email,
            'local_role' => $localRole,
        ];
    }

    /**
     * Resolusi role lokal berdasarkan role SSO.
     */
    private function resolveLocalRole(mixed $roles, string $email): string
    {
        $roleList = is_array($roles) ? $roles : [$roles];

        if (in_array('admin', $roleList) || in_array('dosen', $roleList)) {
            return 'admin';
        }

        if (in_array('operator', $roleList)) {
            return 'operator';
        }

        // Default: member biasa
        return 'member';
    }

    /**
     * Ambil cached M2M token, atau login ulang jika sudah expired.
     */
    public function getCachedM2mToken(): ?string
    {
        $cached = Cache::get('iae_sso_m2m_token');

        if ($cached) {
            return $cached;
        }

        $result = $this->loginM2M();
        return $result['token'] ?? null;
    }
}
