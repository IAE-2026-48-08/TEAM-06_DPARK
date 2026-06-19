<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-IAE-KEY');

        if (!$apiKey || $apiKey !== env('IAE_API_KEY')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. Invalid or missing X-IAE-KEY.',
                'errors'  => null,
            ], 401);
        }

        return $next($request);
    }
}