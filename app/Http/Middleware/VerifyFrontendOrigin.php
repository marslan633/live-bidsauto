<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyFrontendOrigin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = array_filter(array_map('trim', explode(',', env('ALLOWED_API_ORIGINS', ''))));

        $origin  = $request->headers->get('origin');
        $referer = $request->headers->get('referer');
        

        // Allow if dev environment
        if (app()->environment('local')) {
            return $next($request);
        }

        $originAllowed = $origin && in_array($origin, $allowedOrigins, true);
        $refererAllowed = $referer && collect($allowedOrigins)
            ->contains(fn($allowed) => str_starts_with($referer, $allowed));

        if (!$originAllowed && !$refererAllowed) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}