<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class VerifyAccessKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Local dev bypass
        if (app()->environment('local') && $request->header('X-Access-Key') === 'local') {
            return $next($request);
        }

        $apiKey = $request->header('X-Access-Key');

        if (!$apiKey) {
            return response()->json(['message' => 'API key required'], 401);
        }

        $keyExists = DB::table('access_keys')
            ->where('key', $apiKey)
            ->where('active', true)
            ->exists();

        if (!$keyExists) {
            return response()->json(['message' => 'Invalid API key'], 403);
        }

        return $next($request);
    }
}