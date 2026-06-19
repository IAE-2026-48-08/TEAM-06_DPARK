<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\User;
use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyJwtSSO
{
    /**
     * Handle an incoming request.
     *
     * Verifies the Bearer JWT token against the JWKS public key from the IAE SSO,
     * finds or creates the user locally, and maps them to their local role.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Missing or invalid Authorization header'
            ], 401);
        }

        $token = substr($authHeader, 7);

        try {
            // Fetch JWKS from Cloud Dosen (cached for 1 hour)
            $jwks = Cache::remember('iae_sso_jwks', 3600, function () {
                $response = Http::withOptions(['verify' => false])
                    ->get(config('services.iae_sso.url') . '/api/v1/auth/jwks');

                if (!$response->successful()) {
                    throw new \Exception('Failed to fetch JWKS from SSO');
                }

                return $response->json();
            });

            // Decode JWT using RS256 public key from JWKS
            $decoded = JWT::decode($token, JWK::parseKeySet($jwks, 'RS256'));
            $payload = (array) $decoded;

            // Determine user info and role based on token_type
            $tokenType = $payload['token_type'] ?? 'unknown';

            if ($tokenType === 'user') {
                // End-User SSO token
                $profile = (array) ($payload['profile'] ?? []);
                $email = $profile['email'] ?? $payload['sub'];
                $name = $profile['name'] ?? 'Warga SSO';

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => bcrypt(bin2hex(random_bytes(16))),
                    ]
                );

                // Map to 'warga' role
                $this->assignRole($user, 'warga');

            } elseif ($tokenType === 'm2m') {
                // Machine-to-Machine token
                $app = (array) ($payload['app'] ?? []);
                $clientId = $app['client_id'] ?? $payload['sub'];
                $appName = $app['name'] ?? 'M2M Service';

                $user = User::firstOrCreate(
                    ['email' => $clientId . '@m2m.iae.id'],
                    [
                        'name' => $appName,
                        'password' => bcrypt(bin2hex(random_bytes(16))),
                    ]
                );

                // Map to 'admin' role
                $this->assignRole($user, 'admin');

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Unknown token type'
                ], 401);
            }

            // Login the user into Laravel
            Auth::login($user);

            // Attach decoded JWT payload to request for downstream use
            $request->merge([
                'jwt_payload' => $payload,
                'jwt_token' => $token,
            ]);

        } catch (\Firebase\JWT\ExpiredException $e) {
            Log::warning('JWT expired: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Token has expired'
            ], 401);
        } catch (\Exception $e) {
            Log::error('JWT verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid token - ' . $e->getMessage()
            ], 401);
        }

        return $next($request);
    }

    /**
     * Assign a role to the user if not already assigned.
     */
    private function assignRole(User $user, string $roleName): void
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        if (!$user->roles()->where('role_id', $role->id)->exists()) {
            $user->roles()->attach($role);
        }
    }
}
