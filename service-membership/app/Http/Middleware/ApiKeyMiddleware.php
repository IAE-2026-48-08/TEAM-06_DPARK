<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('x-api-key');
        $validKey = env('API_KEY');

        // Pengecualian untuk endpoint dokumentasi swagger agar tetap bisa diakses tanpa header auth
        if ($request->is('api/documentation') || $request->is('docs/*')) {
            return $next($request);
        }

        if (!$apiKey || $apiKey !== $validKey) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Invalid or missing API Key (x-api-key).',
            ], 401);
        }

        return $next($request);
    }
}
