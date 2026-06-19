<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SsoTokenManager
{
    /**
     * Get a valid M2M (Machine-to-Machine) JWT token from the SSO.
     *
     * Cached for 50 minutes to avoid redundant network calls.
     *
     * @return string
     * @throws \Exception
     */
    public static function getM2mToken(): string
    {
        return Cache::remember('iae_sso_m2m_token', 3000, function () {
            $url    = config('services.iae_sso.url') ?: 'https://iae-sso.virtualfri.id';
            $apiKey = config('services.iae_sso.api_key') ?: 'KEY-MHS-424';
            $nim    = config('services.iae_sso.nim') ?: '102022400347';

            Log::info('Fetching fresh M2M token from SSO...', ['url' => $url]);

            $response = Http::withOptions(['verify' => false])
                ->post($url . '/api/v1/auth/token', [
                    'api_key' => $apiKey,
                    'nim'     => $nim,
                ]);

            if (!$response->successful()) {
                Log::error('Failed to retrieve M2M token from SSO', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new \Exception('Failed to retrieve M2M token from SSO: ' . $response->body());
            }

            $data  = $response->json();
            $token = $data['token'] ?? null;

            if (!$token) {
                throw new \Exception('SSO response did not contain a token');
            }

            Log::info('Successfully retrieved and cached M2M token.');

            return $token;
        });
    }
}