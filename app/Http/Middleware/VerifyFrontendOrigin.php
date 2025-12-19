<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyFrontendOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        /**
         * ------------------------------------------------------------
         * 1. Allow LOCAL environment
         * ------------------------------------------------------------
         */
        // if (app()->environment('local')) {
        //     return $next($request);
        // }

        /**
         * ------------------------------------------------------------
         * 2. Allow CORS preflight requests
         * ------------------------------------------------------------
         */
        if ($request->isMethod('OPTIONS')) {
            return response()->noContent();
        }

        /**
         * ------------------------------------------------------------
         * 3. Get allowed origins from ENV
         * ------------------------------------------------------------
         */
        $allowedOrigins = array_filter(
            array_map('trim', explode(',', env('ALLOWED_API_ORIGINS', '')))
        );

        /**
         * ------------------------------------------------------------
         * 4. Read request headers
         * ------------------------------------------------------------
         */
        $origin  = $request->header('Origin');
        $referer = $request->header('Referer');

        /**
         * ------------------------------------------------------------
         * 5. If BOTH headers are missing → allow
         *    (Postman, same-domain, cron, backend calls)
         * ------------------------------------------------------------
         */
        if (!$origin && !$referer) {
            return $next($request);
        }

        /**
         * ------------------------------------------------------------
         * 6. Validate ORIGIN header
         * ------------------------------------------------------------
         */
        $originAllowed = $origin
            && in_array($origin, $allowedOrigins, true);

        /**
         * ------------------------------------------------------------
         * 7. Validate REFERER header
         * ------------------------------------------------------------
         */
        $refererAllowed = $referer
            && collect($allowedOrigins)
                ->contains(fn ($allowed) =>
                    str_starts_with($referer, $allowed)
                );

        /**
         * ------------------------------------------------------------
         * 8. Block if neither is valid
         * ------------------------------------------------------------
         */
        if (!$originAllowed && !$refererAllowed) {
            return response()->json([
                'message' => 'Forbidden – Invalid request origin'
            ], 403);
        }

        /**
         * ------------------------------------------------------------
         * 9. Allow request
         * ------------------------------------------------------------
         */
        return $next($request);
    }
}